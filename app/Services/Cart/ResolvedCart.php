<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Coupon;
use App\Support\Money;

/**
 * The server's authoritative view of a submitted cart.
 *
 * Nothing here is derived from client-supplied prices: every amount is read
 * from the catalog at validation time.
 */
final class ResolvedCart
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $removedItems
     * @param  list<array<string, mixed>>  $adjustments
     * @param  list<array<string, string>>  $issues
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $removedItems,
        public readonly array $adjustments,
        public readonly array $issues,
        public readonly Money $subtotal,
        public readonly Money $discount,
        public readonly ?Coupon $coupon,
        public readonly string $currency,
        public readonly string $cartHash,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function total(): Money
    {
        return $this->subtotal->minus($this->discount)->atLeastZero();
    }

    /**
     * Total weight in grams, or null when no line carries a weight.
     */
    public function weightGrams(): ?int
    {
        $total = 0;
        $known = false;

        foreach ($this->lines as $line) {
            if ($line['weightGrams'] !== null) {
                $known = true;
                $total += (int) $line['weightGrams'] * (int) $line['quantity'];
            }
        }

        return $known ? $total : null;
    }
}
