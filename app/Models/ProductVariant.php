<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'price_amount', 'compare_at_amount', 'stock_quantity',
        'inventory_policy', 'weight_grams', 'media_id', 'is_default', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_amount' => 'integer',
            'stock_quantity' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_value',
            'product_variant_id',
            'product_option_value_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Variant price, falling back to the product price when no override is set.
     */
    protected function price(): Attribute
    {
        return Attribute::get(function (): Money {
            $currency = $this->product?->currency ?? 'USD';

            if ($this->price_amount !== null) {
                return Money::of((int) $this->price_amount, $currency);
            }

            return Money::of((int) ($this->product?->price_amount ?? 0), $currency);
        });
    }

    protected function compareAtPrice(): Attribute
    {
        return Attribute::get(function (): ?Money {
            $currency = $this->product?->currency ?? 'USD';

            if ($this->compare_at_amount !== null) {
                return Money::of((int) $this->compare_at_amount, $currency);
            }

            return null;
        });
    }

    public function effectiveInventoryPolicy(): string
    {
        return $this->inventory_policy ?? $this->product?->inventory_policy ?? Product::POLICY_TRACK;
    }

    public function label(): ?string
    {
        $labels = $this->optionValues->pluck('label')->filter()->all();

        return $labels === [] ? null : implode(' / ', $labels);
    }
}
