<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\AvailabilityResolver;

/**
 * Public product card (api-contract.md 4).
 *
 * This resource emits an explicit field list. `cost_amount`, supplier data,
 * internal notes and margin are structurally absent — they are never read
 * here, so they can never leak into a public response.
 */
final class ProductCardResource
{
    /**
     * @param  array{average: float, count: int}|null  $rating
     * @return array<string, mixed>
     */
    public static function make(
        Product $product,
        AvailabilityResolver $availability,
        ?array $rating = null,
    ): array {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'shortDescription' => $product->short_description,
            'price' => MoneyResource::make($product->price),
            'compareAtPrice' => MoneyResource::nullable($product->displayCompareAtPrice()),
            'primaryImage' => MediaResource::imageRef(self::primaryMedia($product)),
            'availability' => $availability->forProductCard($product),
            // null, never 0, when there are no approved reviews.
            'rating' => $rating,
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(static fn (Category $c): array => CategoryResource::ref($c))->values()->all()
                : [],
            'hasVariants' => self::hasVariants($product),
            'badges' => array_values((array) ($product->badges ?? [])),
        ];
    }

    private static function primaryMedia(Product $product): ?\App\Models\Media
    {
        if ($product->primary_media_id !== null && $product->relationLoaded('primaryMedia')) {
            return $product->primaryMedia;
        }

        if ($product->relationLoaded('media')) {
            return $product->media->first();
        }

        return null;
    }

    private static function hasVariants(Product $product): bool
    {
        if ($product->relationLoaded('variants')) {
            return $product->variants->isNotEmpty();
        }

        return (int) ($product->variants_count ?? 0) > 0;
    }
}
