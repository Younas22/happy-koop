<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'code', 'type', 'value', 'currency', 'min_subtotal_amount',
        'starts_at', 'ends_at', 'usage_limit', 'usage_limit_per_user',
        'used_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_subtotal_amount' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isWithinWindow(): bool
    {
        $now = now();

        if ($this->starts_at !== null && $this->starts_at > $now) {
            return false;
        }

        return ! ($this->ends_at !== null && $this->ends_at < $now);
    }

    public function hasUsesLeft(): bool
    {
        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    /**
     * Discount for a subtotal, never exceeding the subtotal itself.
     */
    public function discountFor(Money $subtotal): Money
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? $subtotal->percentage($this->value)
            : Money::of($this->value, $this->currency ?? $subtotal->currency);

        if ($discount->currency !== $subtotal->currency) {
            return Money::zero($subtotal->currency);
        }

        return $discount->min($subtotal);
    }
}
