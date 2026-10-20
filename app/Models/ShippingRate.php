<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_zone_id', 'code', 'label', 'description', 'price_amount', 'currency',
        'min_subtotal_amount', 'free_above_amount', 'estimate_min_days', 'estimate_max_days',
        'is_default', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'min_subtotal_amount' => 'integer',
            'free_above_amount' => 'integer',
            'estimate_min_days' => 'integer',
            'estimate_max_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function price(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->price_amount, $this->currency));
    }

    /**
     * Charge for this rate given a subtotal, honouring a configured
     * free-shipping threshold. Never invents a free-shipping rule.
     */
    public function chargeFor(Money $subtotal): Money
    {
        if ($this->free_above_amount !== null && $subtotal->amount >= $this->free_above_amount) {
            return Money::zero($this->currency);
        }

        return Money::of((int) $this->price_amount, $this->currency);
    }

    public function availableFor(Money $subtotal): bool
    {
        return $this->min_subtotal_amount === null
            || $subtotal->amount >= $this->min_subtotal_amount;
    }
}
