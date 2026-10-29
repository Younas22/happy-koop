<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Services\Settings\PaymentSettingsService;
use App\Support\Money;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Stripe integration.
 *
 * The secret key and webhook secret are read from encrypted settings at call
 * time and never leave the server. Every amount comes from the STORED order,
 * never from the request (api-contract.md 6).
 */
class StripeGateway
{
    public function __construct(
        private readonly PaymentSettingsService $settings,
    ) {}

    public function isConfigured(): bool
    {
        $credentials = $this->settings->credentials('stripe');

        return ($credentials['secret_key'] ?? null) !== null;
    }

    public function mode(): string
    {
        return $this->settings->mode('stripe');
    }

    public function publishableKey(): ?string
    {
        return $this->settings->credentials('stripe')['publishable_key'] ?? null;
    }

    /**
     * Creates (or reuses) a PaymentIntent for an order.
     *
     * @return array<string, mixed>
     */
    public function createPaymentIntent(Order $order): array
    {
        $client = $this->client();
        $amount = $order->total;

        try {
            $intent = $this->quietly(fn () => $client->paymentIntents->create([
                'amount' => $amount->amount,
                'currency' => strtolower($amount->currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_id' => (string) $order->id,
                ],
                'description' => 'Order '.$order->order_number,
            ]));
        } catch (Throwable $e) {
            throw $this->translate($e);
        }

        return [
            'provider' => 'stripe',
            'mode' => $this->mode(),
            'clientSecret' => $intent->client_secret,
            'paymentIntentId' => $intent->id,
            'publishableKey' => $this->publishableKey(),
            'amount' => $amount->toArray(),
        ];
    }

    /**
     * Hosted Checkout alternative.
     *
     * @return array{url: string|null, sessionId: string}
     */
    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): array
    {
        $client = $this->client();
        $amount = $order->total;

        try {
            $session = $this->quietly(fn () => $client->checkout->sessions->create([
                'mode' => 'payment',
                'client_reference_id' => $order->order_number,
                'customer_email' => $order->email,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'order_id' => (string) $order->id,
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($amount->currency),
                        'unit_amount' => $amount->amount,
                        'product_data' => [
                            'name' => 'Order '.$order->order_number,
                        ],
                    ],
                ]],
            ]));
        } catch (Throwable $e) {
            throw $this->translate($e);
        }

        return [
            'url' => $session->url,
            'sessionId' => $session->id,
        ];
    }

    public function refund(Order $order, Money $amount, string $paymentIntentId): string
    {
        $client = $this->client();

        try {
            $refund = $this->quietly(fn () => $client->refunds->create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amount->amount,
                'metadata' => ['order_number' => $order->order_number],
            ]));
        } catch (Throwable $e) {
            throw $this->translate($e);
        }

        return (string) $refund->id;
    }

    /**
     * Verifies a webhook against the RAW request body and the configured
     * webhook secret, with a configured tolerance.
     *
     * @return array<string, mixed> the decoded event
     */
    public function verifyWebhook(string $rawBody, ?string $signatureHeader): array
    {
        $secret = $this->settings->credentials('stripe')['webhook_secret'] ?? null;

        if ($secret === null) {
            throw ApiException::badRequest('Stripe webhooks are not configured.');
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            throw ApiException::badRequest('Missing Stripe signature header.');
        }

        try {
            $event = Webhook::constructEvent(
                $rawBody,
                $signatureHeader,
                $secret,
                (int) config('happykoop.payments.stripe.webhook_tolerance', 300)
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            // Deliberately generic: never echo provider detail here.
            throw ApiException::badRequest('Invalid webhook signature.');
        }

        return $event->toArray();
    }

    /**
     * Real server-side connection check (Stripe GET /v1/account).
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'No Stripe secret key is configured.'];
        }

        try {
            $client = $this->client();
            $account = $this->quietly(fn () => $client->accounts->retrieve());
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => $this->sanitize($e->getMessage()),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Stripe check failed: '.$this->sanitize($e->getMessage()),
            ];
        }

        return [
            'ok' => true,
            'message' => 'Connected to account '.$account->id.'.',
        ];
    }

    private function client(): StripeClient
    {
        $secret = $this->settings->credentials('stripe')['secret_key'] ?? null;

        if ($secret === null) {
            throw ApiException::paymentNotConfigured('Stripe is not configured.');
        }

        return new StripeClient([
            'api_key' => $secret,
            'stripe_version' => '2024-06-20',
        ]);
    }

    /**
     * Runs a Stripe call. stripe-php reports API notices (e.g. "outdated API
     * version") via trigger_error() AFTER a successful response; Laravel would
     * turn that into an ErrorException and fail the request, so log them.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function quietly(callable $call): mixed
    {
        set_error_handler(function (int $level, string $message): bool {
            Log::warning('Stripe notice: '.$this->sanitize($message));

            return true;
        }, E_USER_WARNING);

        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    private function translate(Throwable $e): ApiException
    {
        if ($e instanceof ApiException) {
            return $e;
        }

        report($e);

        return ApiException::serviceUnavailable(
            'The payment provider could not process this request. Please try again.'
        );
    }

    /**
     * Strips anything that could resemble a credential out of a provider
     * message before it is shown to an administrator.
     */
    private function sanitize(string $message): string
    {
        $message = preg_replace('/\b(sk|pk|rk|whsec)_[A-Za-z0-9_]+/', '[redacted]', $message) ?? '';

        return mb_substr(trim($message), 0, 300);
    }
}
