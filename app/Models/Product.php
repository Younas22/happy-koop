<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const POLICY_TRACK = 'track';

    public const POLICY_CONTINUE = 'continue';

    public const POLICY_DENY = 'deny';

    protected $fillable = [
        'name', 'slug', 'sku', 'brand', 'short_description', 'description', 'status',
        'price_amount', 'compare_at_amount', 'cost_amount', 'currency',
        'inventory_policy', 'stock_quantity', 'low_stock_threshold',
        'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'value_props', 'whats_included', 'shipping_summary', 'returns_summary',
        'warranty_text', 'returns_window_days', 'badges', 'primary_media_id',
        'seo_title', 'seo_description', 'seo_noindex', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_amount' => 'integer',
            'cost_amount' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'returns_window_days' => 'integer',
            'value_props' => 'json',
            'whats_included' => 'json',
            'badges' => 'json',
            'seo_noindex' => 'boolean',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /* ----------------------------------------------------------- relations */

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media')
            ->withPivot(['sort_order'])
            ->orderBy('product_media.sort_order');
    }

    public function primaryMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'primary_media_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('sort_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot(['sort_order'])
            ->orderBy('product_related.sort_order');
    }

    /* -------------------------------------------------------------- scopes */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /* ------------------------------------------------------------ accessors */

    protected function price(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->price_amount, $this->currency));
    }

    protected function compareAtPrice(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->compare_at_amount === null
            ? null
            : Money::of((int) $this->compare_at_amount, $this->currency));
    }

    /**
     * PRIVATE. Admin surfaces only; never serialized by a public resource.
     */
    protected function costPrice(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->cost_amount === null
            ? null
            : Money::of((int) $this->cost_amount, $this->currency));
    }

    /* ------------------------------------------------------------- helpers */

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at <= now());
    }

    public function hasVariants(): bool
    {
        return $this->variants->isNotEmpty();
    }

    /**
     * Compare-at price is only meaningful when strictly greater than price.
     */
    public function displayCompareAtPrice(): ?Money
    {
        $compare = $this->compare_at_price;

        return $compare !== null && $compare->greaterThan($this->price) ? $compare : null;
    }
}
