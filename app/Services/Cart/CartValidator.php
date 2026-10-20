<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalog\CatalogContext;
use App\Support\Money;

/**
 * Validates a client-held cart against the catalog (api-contract.md 5).
 *
 * The browser sends ids and quantities only. Every price, availability state,
 * discount and total is computed here; any monetary value in the request is
 * ignored.
 */
class CartValidator
{
    public function __construct(
        private readonly CatalogContext $context,
    ) {}

    /**
     * @param  list<array{productId: int, variantId?: int|null, quantity: int}>  $items
     */
    public function resolve(array $items, ?string $couponCode = null, ?User $user = null): ResolvedCart
    {
        $currency = $this->context->currency();
        $availability = $this->context->availability();
        $maxPerLine = (int) config('happykoop.checkout.max_quantity_per_line', 99);

        $lines = [];
        $removed = [];
        $adjustments = [];
        $issues = [];

        foreach ($this->mergeDuplicates($items) as $key => $requested) {
            $productId = (int) $requested['productId'];
            $variantId = $requested['variantId'] === null ? null : (int) $requested['variantId'];
            $quantity = (int) $requested['quantity'];

            /** @var Product|null $product */
            $product = Product::query()
                ->published()
                ->with(['primaryMedia', 'media'])
                ->find($productId);

            if ($product === null) {
                $removed[] = [
                    'productId' => $productId,
                    'variantId' => $variantId,
                    'reason' => 'unpublished',
                    'message' => 'This product is no longer available.',
                ];

                continue;
            }

            if ($product->currency !== $currency) {
                $removed[] = [
                    'productId' => $productId,
                    'variantId' => $variantId,
                    'reason' => 'currency_mismatch',
                    'message' => 'This product cannot be purchased in the store currency.',
                ];

                continue;
            }

            $variant = null;

            if ($variantId !== null) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()
                    ->with(['product', 'optionValues.option', 'media'])
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->find($variantId);

                if ($variant === null) {
                    $removed[] = [
                        'productId' => $productId,
                        'variantId' => $variantId,
                        'reason' => 'variant_unavailable',
                        'message' => 'The selected option is no longer available.',
                    ];

                    continue;
                }
            } elseif ($product->variants()->where('is_active', true)->exists()) {
                $removed[] = [
                    'productId' => $productId,
                    'variantId' => null,
                    'reason' => 'variant_required',
                    'message' => 'Please choose an option for this product.',
                ];

                continue;
            }

            $unitPrice = $variant !== null ? $variant->price : $product->price;
            $policy = $variant !== null
                ? $variant->effectiveInventoryPolicy()
                : (string) $product->inventory_policy;
            $stock = $variant !== null
                ? (int) $variant->stock_quantity
                : $availability->effectiveStock($product);

            $maxQuantity = $policy === Product::POLICY_CONTINUE
                ? $maxPerLine
                : max(0, min($stock, $maxPerLine));

            if ($maxQuantity <= 0) {
                $removed[] = [
                    'productId' => $productId,
                    'variantId' => $variantId,
                    'reason' => 'out_of_stock',
                    'message' => 'This item is out of stock.',
                ];

                continue;
            }

            $lineIssues = [];

            if ($quantity > $maxQuantity) {
                $adjustments[] = [
                    'key' => $key,
                    'field' => 'quantity',
                    'from' => $quantity,
                    'to' => $maxQuantity,
                    'reason' => 'insufficient_stock',
                    'message' => $maxQuantity === 1
                        ? 'Only 1 left.'
                        : "Only {$maxQuantity} left.",
                ];

                $quantity = $maxQuantity;
            }

            $state = $variant !== null
                ? $availability->forVariant($variant)
                : $availability->forProductCard($product);

            $lines[] = [
                'key' => $key,
                'productId' => (int) $product->id,
                'variantId' => $variant === null ? null : (int) $variant->id,
                'name' => $product->name,
                'variantLabel' => $variant?->label(),
                'sku' => $variant?->sku ?? $product->sku,
                'slug' => $product->slug,
                'imageUrl' => ($variant?->media ?? $product->primaryMedia ?? $product->media->first())?->url(),
                'quantity' => $quantity,
                'maxQuantity' => $maxQuantity,
                'unitPrice' => $unitPrice,
                'lineTotal' => $unitPrice->times($quantity),
                'availability' => ['inStock' => $state['inStock'], 'state' => $state['state']],
                'weightGrams' => $variant?->weight_grams ?? $product->weight_grams,
                'options' => $this->optionSnapshot($variant),
                'issues' => $lineIssues,
            ];
        }

        $subtotal = Money::zero($currency);

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line['lineTotal']);
        }

        [$coupon, $discount, $couponIssue] = $this->applyCoupon($couponCode, $subtotal, $user);

        if ($couponIssue !== null) {
            $issues[] = $couponIssue;
        }

        return new ResolvedCart(
            lines: $lines,
            removedItems: $removed,
            adjustments: $adjustments,
            issues: $issues,
            subtotal: $subtotal,
            discount: $discount,
            coupon: $coupon,
            currency: $currency,
            cartHash: $this->hash($lines, $currency),
        );
    }

    /**
     * Fingerprint over (variant ids, product ids, quantities, unit prices,
     * currency). Checkout recomputes it and rejects a mismatch with
     * 422 CART_CHANGED.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function hash(array $lines, string $currency): string
    {
        $parts = array_map(
            static fn (array $line): string => sprintf(
                '%d:%s:%d:%d',
                $line['productId'],
                $line['variantId'] === null ? '-' : (string) $line['variantId'],
                $line['quantity'],
                $line['unitPrice']->amount,
            ),
            $lines
        );

        sort($parts);

        return 'sha256:'.hash('sha256', $currency.'|'.implode('|', $parts));
    }

    /**
     * The full response payload for POST /cart/validate.
     *
     * @return array<string, mixed>
     */
    public function toResponse(ResolvedCart $cart): array
    {
        return [
            'items' => array_map(static fn (array $line): array => [
                'key' => $line['key'],
                'productId' => $line['productId'],
                'variantId' => $line['variantId'],
                'name' => $line['name'],
                'variantLabel' => $line['variantLabel'],
                'sku' => $line['sku'],
                'slug' => $line['slug'],
                'imageUrl' => $line['imageUrl'],
                'quantity' => $line['quantity'],
                'maxQuantity' => $line['maxQuantity'],
                'unitPrice' => $line['unitPrice']->toArray(),
                'lineTotal' => $line['lineTotal']->toArray(),
                'availability' => $line['availability'],
                'issues' => $line['issues'],
            ], $cart->lines),
            'removedItems' => $cart->removedItems,
            'adjustments' => $cart->adjustments,
            'issues' => $cart->issues,
            'totals' => [
                'subtotal' => $cart->subtotal->toArray(),
                'discount' => $cart->discount->toArray(),
                // null = "not yet determined". Never rendered as $0.00.
                'shipping' => null,
                'tax' => null,
                'total' => $cart->total()->toArray(),
            ],
            'coupon' => $cart->coupon === null ? null : [
                'code' => $cart->coupon->code,
                'type' => $cart->coupon->type,
                'discount' => $cart->discount->toArray(),
            ],
            'currency' => $cart->currency,
            'cartHash' => $cart->cartHash,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, array{productId: int, variantId: int|null, quantity: int}>
     */
    private function mergeDuplicates(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $variantId = isset($item['variantId']) && $item['variantId'] !== null
                ? (int) $item['variantId']
                : null;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $key = $productId.':'.($variantId ?? '');

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $quantity;

                continue;
            }

            $merged[$key] = [
                'productId' => $productId,
                'variantId' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $merged;
    }

    /**
     * @return array{0: Coupon|null, 1: Money, 2: array{code: string, message: string}|null}
     */
    private function applyCoupon(?string $code, Money $subtotal, ?User $user): array
    {
        $code = $code === null ? null : trim($code);

        if ($code === null || $code === '') {
            return [null, Money::zero($subtotal->currency), null];
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->active()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        $reject = static fn (string $message): array => [
            null,
            Money::zero($subtotal->currency),
            ['code' => 'COUPON_INVALID', 'message' => $message],
        ];

        if ($coupon === null || ! $coupon->isWithinWindow() || ! $coupon->hasUsesLeft()) {
            return $reject('This discount code is not valid.');
        }

        if ($coupon->min_subtotal_amount !== null && $subtotal->amount < $coupon->min_subtotal_amount) {
            return $reject('This discount code requires a larger order.');
        }

        if ($user !== null && $coupon->usage_limit_per_user !== null) {
            $used = Order::query()
                ->where('user_id', $user->id)
                ->whereRaw('UPPER(coupon_code) = ?', [strtoupper($coupon->code)])
                ->whereIn('payment_status', [Order::PAYMENT_PAID, Order::PAYMENT_AUTHORIZED])
                ->count();

            if ($used >= $coupon->usage_limit_per_user) {
                return $reject('You have already used this discount code.');
            }
        }

        $discount = $coupon->discountFor($subtotal);

        if (! $discount->isPositive()) {
            return $reject('This discount code does not apply to your cart.');
        }

        return [$coupon, $discount, null];
    }

    /**
     * @return list<array{name: string, value: string}>|null
     */
    private function optionSnapshot(?ProductVariant $variant): ?array
    {
        if ($variant === null) {
            return null;
        }

        $out = [];

        foreach ($variant->optionValues as $value) {
            $out[] = [
                'name' => (string) ($value->option?->name ?? ''),
                'value' => (string) $value->label,
            ];
        }

        return $out === [] ? null : $out;
    }
}
