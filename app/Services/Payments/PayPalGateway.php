<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Services\Settings\PaymentSettingsService;
use App\Support\CurrencyRegistry;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * PayPal REST integration (OAuth token -> create order -> capture).
 *
 * The client secret is read from encrypted settings at call time. After a
 * capture the result is verified server-side — status, amount, currency and
 * the order reference — before any payment state changes
 * (api-contract.md 6).
 */
class PayPalGateway
{
    public function __construct(
        private readonly PaymentSettingsService $settings,
    ) {}

    public function isConfigured(): bool
    {
        $credentials = $this->settings->credentials('paypal');

        return ($credentials['client_id'] ?? null) !== null
            && ($credentials['client_secret'] ?? null) !== null;
    }

    public function mode(): string
    {
        return $this->settings->mode('paypal');
    }

    public function baseUrl(): string
    {
        return $this->mode() === 'live'
            ? (string) config('happykoop.payments.paypal.live_base')
            : (string) config('happykoop.payments.paypal.sandbox_base');
    }

    /**
     * Creates a PayPal order from the STORED order total.
     *
     * @return array<string, mixed>
     */
    public function createOrder(Order $order, string $returnUrl, string $cancelUrl): array
    {
        $amount = $order->total;

        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'custom_id' => $order->order_number,
                'invoice_id' => $order->order_number,
                'amount' => [
                    'currency_code' => $amount->currency,
                    'value' => $this->decimalString($amount),
                ],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ]);

        if (! $response->successful()) {
            throw $this->translate('create-order', $response->status());
        }

        return [
            'provider' => 'paypal',
            'mode' => $this->mode(),
            'paypalOrderId' => (string) $response->json('id'),
            'status' => (string) $response->json('status'),
            'amount' => $amount->toArray(),
        ];
    }

    /**
     * Captures a PayPal order and INDEPENDENTLY verifies the outcome against
     * the stored order before the caller changes any state.
     *
     * @return array{verified: bool, status: string, reference: string|null, reason: string|null}
     */
    public function captureOrder(Order $order, string $paypalOrderId): array
    {
        $response = $this->client()->post("/v2/checkout/orders/{$paypalOrderId}/capture");

        if (! $response->successful()) {
            // An already-captured order is not a failure: re-read and verify.
            $lookup = $this->client()->get("/v2/checkout/orders/{$paypalOrderId}");

            if (! $lookup->successful()) {
                throw $this->translate('capture', $response->status());
            }

            return $this->verifyPayload($order, $lookup->json() ?? []);
        }

        return $this->verifyPayload($order, $response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{verified: bool, status: string, reference: string|null, reason: string|null}
     */
    public function verifyPayload(Order $order, array $payload): array
    {
        $status = (string) ($payload['status'] ?? 'UNKNOWN');
        $unit = $payload['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? null;

        $reference = $capture['id'] ?? ($payload['id'] ?? null);

        if ($status !== 'COMPLETED' && ($capture['status'] ?? null) !== 'COMPLETED') {
            return [
                'verified' => false,
                'status' => $status,
                'reference' => $reference === null ? null : (string) $reference,
                'reason' => 'not_completed',
            ];
        }

        $amountNode = $capture['amount'] ?? ($unit['amount'] ?? []);
        $currency = strtoupper((string) ($amountNode['currency_code'] ?? ''));
        $value = (string) ($amountNode['value'] ?? '');

        if ($currency !== $order->currency) {
            return [
                'verified' => false,
                'status' => $status,
                'reference' => $reference === null ? null : (string) $reference,
                'reason' => 'currency_mismatch',
            ];
        }

        if ($this->minorUnits($value, $currency) !== (int) $order->total_amount) {
            return [
                'verified' => false,
                'status' => $status,
                'reference' => $reference === null ? null : (string) $reference,
                'reason' => 'amount_mismatch',
            ];
        }

        $declaredReference = (string) ($unit['custom_id'] ?? $unit['reference_id'] ?? $unit['invoice_id'] ?? '');

        if ($declaredReference !== '' && $declaredReference !== $order->order_number) {
            return [
                'verified' => false,
                'status' => $status,
                'reference' => $reference === null ? null : (string) $reference,
                'reason' => 'reference_mismatch',
            ];
        }

        return [
            'verified' => true,
            'status' => $status,
            'reference' => $reference === null ? null : (string) $reference,
            'reason' => null,
        ];
    }

    public function refund(Money $amount, string $captureId): string
    {
        $response = $this->client()->post("/v2/payments/captures/{$captureId}/refund", [
            'amount' => [
                'currency_code' => $amount->currency,
                'value' => $this->decimalString($amount),
            ],
        ]);

        if (! $response->successful()) {
            throw $this->translate('refund', $response->status());
        }

        return (string) $response->json('id');
    }

    /**
     * Verifies a webhook through PayPal's verify-webhook-signature API using
     * the stored webhook id.
     *
     * @param  array<string, string|null>  $headers
     */
    public function verifyWebhookSignature(array $headers, string $rawBody): bool
    {
        $webhookId = $this->settings->credentials('paypal')['webhook_id'] ?? null;

        if ($webhookId === null) {
            return false;
        }

        $required = [
            'transmission_id' => $headers['paypal-transmission-id'] ?? null,
            'transmission_time' => $headers['paypal-transmission-time'] ?? null,
            'cert_url' => $headers['paypal-cert-url'] ?? null,
            'auth_algo' => $headers['paypal-auth-algo'] ?? null,
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
        ];

        foreach ($required as $value) {
            if ($value === null || $value === '') {
                return false;
            }
        }

        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return false;
        }

        try {
            $response = $this->client()->post('/v1/notifications/verify-webhook-signature', $required + [
                'webhook_id' => $webhookId,
                'webhook_event' => $decoded,
            ]);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return $response->successful()
            && (string) $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * Real server-side check: request an OAuth token.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'No PayPal client id and secret are configured.'];
        }

        try {
            $token = $this->accessToken();
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'PayPal rejected the configured credentials.'];
        }

        return $token === null
            ? ['ok' => false, 'message' => 'PayPal rejected the configured credentials.']
            : ['ok' => true, 'message' => 'Authenticated with PayPal ('.$this->mode().' mode).'];
    }

    public function accessToken(): ?string
    {
        $credentials = $this->settings->credentials('paypal');
        $clientId = $credentials['client_id'] ?? null;
        $secret = $credentials['client_secret'] ?? null;

        if ($clientId === null || $secret === null) {
            throw ApiException::paymentNotConfigured('PayPal is not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->timeout((int) config('happykoop.payments.http_timeout', 20))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');

        return is_string($token) ? $token : null;
    }

    private function client(): PendingRequest
    {
        $token = $this->accessToken();

        if ($token === null) {
            throw ApiException::serviceUnavailable('PayPal authentication failed.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('happykoop.payments.http_timeout', 20))
            ->baseUrl($this->baseUrl());
    }

    /**
     * Minor units -> PayPal's decimal string, using integer maths only.
     */
    public function decimalString(Money $money): string
    {
        $exponent = CurrencyRegistry::minorUnitExponent($money->currency);

        if ($exponent === 0) {
            return (string) $money->amount;
        }

        $digits = str_pad((string) abs($money->amount), $exponent + 1, '0', STR_PAD_LEFT);

        return ($money->amount < 0 ? '-' : '')
            .substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }

    /**
     * PayPal decimal string -> minor units, without float rounding.
     */
    public function minorUnits(string $value, string $currency): int
    {
        $exponent = CurrencyRegistry::minorUnitExponent($currency);
        $value = trim($value);

        if ($value === '' || preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            return -1;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        $fraction = substr(str_pad($fraction, $exponent, '0'), 0, $exponent);

        $minor = (int) ($whole.$fraction);

        return $negative ? -$minor : $minor;
    }

    private function translate(string $operation, int $status): ApiException
    {
        report(new \RuntimeException("PayPal {$operation} failed with HTTP {$status}."));

        return ApiException::serviceUnavailable(
            'The payment provider could not process this request. Please try again.'
        );
    }
}
