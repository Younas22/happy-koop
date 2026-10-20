<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Inventory is committed exactly once per order, transactionally, on the first
 * transition into paid/authorized (api-contract.md 6, decision 7).
 *
 * `orders.inventory_committed_at` is the idempotency guard: a webhook replay,
 * a manual admin transition and a capture callback can all arrive for the same
 * order and stock still moves only once.
 */
class InventoryService
{
    /**
     * @return bool true when this call performed the commit
     */
    public function commit(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            /** @var Order|null $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if ($locked === null || $locked->inventory_committed_at !== null) {
                return false;
            }

            foreach ($locked->items()->get() as $item) {
                $this->decrementForItem($item);
            }

            $locked->forceFill(['inventory_committed_at' => now()])->save();

            $order->setAttribute('inventory_committed_at', $locked->inventory_committed_at);

            return true;
        });
    }

    /**
     * Returns stock to the catalog for a cancelled or refunded order, once.
     *
     * @return bool true when this call performed the release
     */
    public function release(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            /** @var Order|null $locked */
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if ($locked === null || $locked->inventory_committed_at === null) {
                return false;
            }

            foreach ($locked->items()->get() as $item) {
                $this->incrementForItem($item);
            }

            $locked->forceFill(['inventory_committed_at' => null])->save();

            $order->setAttribute('inventory_committed_at', null);

            return true;
        });
    }

    private function decrementForItem(OrderItem $item): void
    {
        $quantity = (int) $item->quantity;

        if ($quantity <= 0) {
            return;
        }

        if ($item->product_variant_id !== null) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->find($item->product_variant_id);

            if ($variant !== null && $variant->effectiveInventoryPolicy() !== Product::POLICY_CONTINUE) {
                $variant->forceFill([
                    'stock_quantity' => (int) $variant->stock_quantity - $quantity,
                ])->save();
            }

            return;
        }

        if ($item->product_id === null) {
            return;
        }

        /** @var Product|null $product */
        $product = Product::query()->lockForUpdate()->find($item->product_id);

        if ($product !== null && $product->inventory_policy !== Product::POLICY_CONTINUE) {
            $product->forceFill([
                'stock_quantity' => (int) $product->stock_quantity - $quantity,
            ])->save();
        }
    }

    private function incrementForItem(OrderItem $item): void
    {
        $quantity = (int) $item->quantity;

        if ($quantity <= 0) {
            return;
        }

        if ($item->product_variant_id !== null) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->find($item->product_variant_id);

            if ($variant !== null && $variant->effectiveInventoryPolicy() !== Product::POLICY_CONTINUE) {
                $variant->forceFill([
                    'stock_quantity' => (int) $variant->stock_quantity + $quantity,
                ])->save();
            }

            return;
        }

        if ($item->product_id === null) {
            return;
        }

        /** @var Product|null $product */
        $product = Product::query()->lockForUpdate()->find($item->product_id);

        if ($product !== null && $product->inventory_policy !== Product::POLICY_CONTINUE) {
            $product->forceFill([
                'stock_quantity' => (int) $product->stock_quantity + $quantity,
            ])->save();
        }
    }
}
