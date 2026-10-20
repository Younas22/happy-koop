<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable purchase snapshot. Prices and labels are frozen at checkout and
 * are never recalculated from the catalog.
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'name', 'variant_label',
        'sku', 'options', 'quantity', 'unit_price_amount', 'line_total_amount',
        'currency', 'weight_grams',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'quantity' => 'integer',
            'unit_price_amount' => 'integer',
            'line_total_amount' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function unitPrice(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->unit_price_amount, $this->currency));
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->line_total_amount, $this->currency));
    }
}
