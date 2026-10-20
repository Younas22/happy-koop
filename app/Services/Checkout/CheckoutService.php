<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Exceptions\ApiException;
use App\Http\Resources\OrderResource;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Cart\CartValidator;
use App\Services\Cart\ResolvedCart;
use App\Services\Settings\PaymentSettingsService;
use App\Services\Shipping\ShippingService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the pending order for a checkout (api-contract.md 5).
 *
 * Runs inside a transaction, re-validates everything server-side, freezes
 * immutable item snapshots, and does NOT touch inventory — stock is committed
 * only once payment is confirmed.
 */
class CheckoutService
{
    public const SCOPE = 'checkout.initialize';

    public function __construct(
        private readonly CartValidator $cart,
        private readonly ShippingService $shipping,
        private readonly PaymentSettingsService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request body
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function initialize(array $input, ?User $user, string $idempotencyKey): array
    {
        $requestHash = $this->hashRequest($input);

        $existing = IdempotencyKey::query()
            ->where('key', $idempotencyKey)
            ->where('scope', self::SCOPE)
            ->first();

        if ($existing !== null) {
            return $this->replay($existing, $requestHash);
        }

        try {
            $record = IdempotencyKey::query()->create([
                'key' => $idempotencyKey,
                'scope' => self::SCOPE,
                'request_hash' => $requestHash,
                'status' => IdempotencyKey::STATUS_IN_PROGRESS,
                'expires_at' => Carbon::now()->addHours(
                    (int) config('happykoop.checkout.idempotency_ttl_hours', 24)
                ),
            ]);
        } catch (QueryException) {
            // Concurrent request with the same key won the insert race.
            $existing = IdempotencyKey::query()
                ->where('key', $idempotencyKey)
                ->where('scope', self::SCOPE)
                ->firstOrFail();

            return $this->replay($existing, $requestHash);
        }

        $payload = DB::transaction(fn (): array => $this->createOrder($input, $user, $record));

        return ['payload' => $payload, 'status' => 201];
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    private function replay(IdempotencyKey $record, string $requestHash): array
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            throw ApiException::conflict(
                'This idempotency key was already used with a different request.'
            );
        }

        if ($record->status !== IdempotencyKey::STATUS_COMPLETED || $record->response_body === null) {
            throw ApiException::conflict('This checkout is still being processed. Please retry shortly.');
        }

        return ['payload' => (array) $record->response_body, 'status' => 200];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createOrder(array $input, ?User $user, IdempotencyKey $record): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = (array) ($input['items'] ?? []);

        $cart = $this->cart->resolve($items, $input['couponCode'] ?? null, $user);

        if ($cart->isEmpty()) {
            throw ApiException::cartChanged('Your cart is empty or no longer available.');
        }

        // Anything the server had to remove or adjust means the client's view
        // is stale: stop and let the customer confirm the new cart.
        if ($cart->removedItems !== [] || $cart->adjustments !== []) {
            throw ApiException::cartChanged();
        }

        $submittedHash = (string) ($input['cartHash'] ?? '');

        if (! hash_equals($cart->cartHash, $submittedHash)) {
            throw ApiException::cartChanged();
        }

        $provider = (string) ($input['paymentProvider'] ?? '');

        if (! in_array($provider, PaymentSettingsService::PROVIDERS, true)
            || ! $this->payments->isEnabled($provider)) {
            throw ApiException::paymentNotConfigured();
        }

        $shippingAddress = (array) $input['shippingAddress'];
        $billingAddress = isset($input['billingAddress']) && is_array($input['billingAddress'])
            ? $input['billingAddress']
            : $shippingAddress;

        $countryCode = strtoupper((string) $shippingAddress['countryCode']);

        $rate = $this->shipping->resolveRate(
            (string) $input['shippingMethodId'],
            $countryCode,
            $cart->subtotal
        );

        if ($rate === null) {
            throw ApiException::validation([
                'shippingMethodId' => ['The selected shipping method is not available for this destination.'],
            ]);
        }

        $shippingCharge = $rate->chargeFor($cart->subtotal);

        $total = $cart->subtotal
            ->minus($cart->discount)
            ->atLeastZero()
            ->plus($shippingCharge);

        $order = Order::query()->create([
            'order_number' => 'PENDING-'.Str::random(16),
            'access_token' => Str::random(64),
            'user_id' => $user?->id,
            'email' => strtolower(trim((string) $input['email'])),
            'phone' => $input['phone'] ?? null,
            'currency' => $cart->currency,
            'subtotal_amount' => $cart->subtotal->amount,
            'discount_amount' => $cart->discount->amount,
            'shipping_amount' => $shippingCharge->amount,
            // No tax engine in v1: null means "not determined", never a
            // presented tax calculation (api-contract.md decision 5).
            'tax_amount' => null,
            'total_amount' => $total->amount,
            'coupon_code' => $cart->coupon?->code,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_UNPAID,
            'payment_provider' => $provider,
            'shipping_method_code' => $rate->code,
            'shipping_method_label' => $rate->label,
            'shipping_address' => $this->normalizeAddress($shippingAddress),
            'billing_address' => $this->normalizeAddress($billingAddress),
            'customer_note' => $input['customerNote'] ?? null,
            'expires_at' => Carbon::now()->addMinutes(
                (int) config('happykoop.checkout.expires_after_minutes', 60)
            ),
        ]);

        $order->forceFill([
            'order_number' => $this->orderNumber($order),
        ])->save();

        $this->createItems($order, $cart);

        if ($user !== null && array_key_exists('acceptsMarketing', $input)) {
            $user->forceFill(['accepts_marketing' => (bool) $input['acceptsMarketing']])->saveQuietly();
        }

        $attempt = PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'mode' => $this->payments->mode($provider),
            'status' => PaymentAttempt::STATUS_REQUIRES_PAYMENT,
            'amount' => $total->amount,
            'currency' => $cart->currency,
        ]);

        $order->load(['items.product.primaryMedia']);

        $payload = [
            'order' => OrderResource::withAccessToken($order),
            'paymentAttempt' => [
                'id' => (int) $attempt->id,
                'provider' => $attempt->provider,
                'status' => $attempt->status,
            ],
        ];

        $record->forceFill([
            'order_id' => $order->id,
            'response_body' => $payload,
            'status' => IdempotencyKey::STATUS_COMPLETED,
        ])->save();

        return $payload;
    }

    private function createItems(Order $order, ResolvedCart $cart): void
    {
        foreach ($cart->lines as $line) {
            /** @var Money $unitPrice */
            $unitPrice = $line['unitPrice'];
            /** @var Money $lineTotal */
            $lineTotal = $line['lineTotal'];

            $order->items()->create([
                'product_id' => $line['productId'],
                'product_variant_id' => $line['variantId'],
                'name' => $line['name'],
                'variant_label' => $line['variantLabel'],
                'sku' => $line['sku'],
                'options' => $line['options'],
                'quantity' => $line['quantity'],
                'unit_price_amount' => $unitPrice->amount,
                'line_total_amount' => $lineTotal->amount,
                'currency' => $unitPrice->currency,
                'weight_grams' => $line['weightGrams'],
            ]);
        }
    }

    private function orderNumber(Order $order): string
    {
        return sprintf('HK-%s-%06d', Carbon::now()->format('Y'), (int) $order->id);
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private function normalizeAddress(array $address): array
    {
        $keys = [
            'firstName', 'lastName', 'company', 'line1', 'line2',
            'city', 'region', 'postalCode', 'countryCode', 'phone',
        ];

        $out = [];

        foreach ($keys as $key) {
            $value = $address[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $out[$key] = $value === '' ? null : $value;
        }

        $out['countryCode'] = $out['countryCode'] === null ? null : strtoupper((string) $out['countryCode']);

        return $out;
    }

    /**
     * Canonical fingerprint of the request body, so the same key with a
     * different body can be rejected with 409.
     *
     * @param  array<string, mixed>  $input
     */
    private function hashRequest(array $input): string
    {
        $canonical = [
            'items' => array_map(static fn (array $item): array => [
                'productId' => (int) ($item['productId'] ?? 0),
                'variantId' => isset($item['variantId']) ? (int) $item['variantId'] : null,
                'quantity' => (int) ($item['quantity'] ?? 0),
            ], (array) ($input['items'] ?? [])),
            'cartHash' => (string) ($input['cartHash'] ?? ''),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'shippingAddress' => $this->normalizeAddress((array) ($input['shippingAddress'] ?? [])),
            'billingAddress' => isset($input['billingAddress']) && is_array($input['billingAddress'])
                ? $this->normalizeAddress($input['billingAddress'])
                : null,
            'shippingMethodId' => (string) ($input['shippingMethodId'] ?? ''),
            'couponCode' => $input['couponCode'] ?? null,
            'paymentProvider' => (string) ($input['paymentProvider'] ?? ''),
        ];

        usort(
            $canonical['items'],
            static fn (array $a, array $b): int => [$a['productId'], $a['variantId']] <=> [$b['productId'], $b['variantId']]
        );

        return hash('sha256', (string) json_encode($canonical));
    }
}
