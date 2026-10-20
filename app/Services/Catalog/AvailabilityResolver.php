<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Derives the availability block of the contract (api-contract.md 4).
 *
 * `low_stock` is emitted only when an administrator configured a real
 * threshold and tracked inventory is at or below it — never as an urgency
 * device. `quantity` is null unless public stock counts are switched on.
 */
class AvailabilityResolver
{
    public const IN_STOCK = 'in_stock';

    public const LOW_STOCK = 'low_stock';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const PREORDER = 'preorder';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly bool $publicStockCounts = false,
    ) {}

    /**
     * @return array{inStock: bool, state: string}
     */
    public function forProductCard(Product $product): array
    {
        $state = $this->state(
            $product->inventory_policy,
            $this->effectiveStock($product),
            $product->low_stock_threshold
        );

        return [
            'inStock' => $this->isPurchasable($state),
            'state' => $state,
        ];
    }

    /**
     * @return array{inStock: bool, state: string, quantity: int|null, policy: string}
     */
    public function forProductDetail(Product $product): array
    {
        $policy = (string) $product->inventory_policy;
        $stock = $this->effectiveStock($product);
        $state = $this->state($policy, $stock, $product->low_stock_threshold);

        return [
            'inStock' => $this->isPurchasable($state),
            'state' => $state,
            'quantity' => $this->publicQuantity($policy, $stock),
            'policy' => $policy,
        ];
    }

    /**
     * @return array{inStock: bool, state: string, quantity: int|null, policy: string}
     */
    public function forVariant(ProductVariant $variant): array
    {
        $policy = $variant->effectiveInventoryPolicy();
        $stock = (int) $variant->stock_quantity;
        $threshold = $variant->product?->low_stock_threshold;

        $state = $variant->is_active === false
            ? self::UNAVAILABLE
            : $this->state($policy, $stock, $threshold);

        return [
            'inStock' => $this->isPurchasable($state),
            'state' => $state,
            'quantity' => $this->publicQuantity($policy, $stock),
            'policy' => $policy,
        ];
    }

    public function state(string $policy, int $stock, ?int $threshold): string
    {
        if ($policy === Product::POLICY_CONTINUE) {
            // Backorder-friendly: the store accepts orders beyond stock.
            return $stock > 0 ? self::IN_STOCK : self::PREORDER;
        }

        if ($stock <= 0) {
            return self::OUT_OF_STOCK;
        }

        if ($threshold !== null && $threshold > 0 && $stock <= $threshold) {
            return self::LOW_STOCK;
        }

        return self::IN_STOCK;
    }

    public function isPurchasable(string $state): bool
    {
        return in_array($state, [self::IN_STOCK, self::LOW_STOCK, self::PREORDER], true);
    }

    /**
     * Stock a simple product sells from; for a product with variants the sum
     * of its active variants.
     */
    public function effectiveStock(Product $product): int
    {
        if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            return (int) $product->variants
                ->where('is_active', true)
                ->sum('stock_quantity');
        }

        return (int) $product->stock_quantity;
    }

    private function publicQuantity(string $policy, int $stock): ?int
    {
        if (! $this->publicStockCounts || $policy !== Product::POLICY_TRACK) {
            return null;
        }

        return max(0, $stock);
    }
}
