<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Services\Catalog\AvailabilityResolver;
use App\Support\Money;

/**
 * Public product detail (api-contract.md 4).
 *
 * Explicit field list only. Cost price, supplier identity/metrics, wholesale
 * tiers, internal notes and margin are never read by this resource.
 */
final class ProductDetailResource
{
    /**
     * @param  array{average: float, count: int}|null  $rating
     * @param  array<string, mixed>  $storeDefaults  shipping/returns fallbacks from store settings
     * @return array<string, mixed>
     */
    public static function make(
        Product $product,
        AvailabilityResolver $availability,
        ?array $rating = null,
        array $storeDefaults = [],
    ): array {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'status' => $product->status,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'valueProps' => array_values((array) ($product->value_props ?? [])),
            'price' => MoneyResource::make($product->price),
            'compareAtPrice' => MoneyResource::nullable($product->displayCompareAtPrice()),
            'availability' => $availability->forProductDetail($product),
            'rating' => $rating,
            'reviewCount' => (int) ($rating['count'] ?? 0),
            'media' => self::media($product),
            'options' => self::options($product),
            'variants' => self::variants($product, $availability),
            'specificationGroups' => self::specificationGroups($product),
            'whatsIncluded' => array_values((array) ($product->whats_included ?? [])),
            'shippingInfo' => [
                'summary' => $product->shipping_summary ?? ($storeDefaults['shippingSummary'] ?? null),
                'estimateText' => $storeDefaults['estimateText'] ?? null,
                'freeShippingThreshold' => isset($storeDefaults['freeShippingThreshold'])
                    && $storeDefaults['freeShippingThreshold'] instanceof Money
                        ? MoneyResource::make($storeDefaults['freeShippingThreshold'])
                        : null,
            ],
            'returnsInfo' => [
                'summary' => $product->returns_summary ?? ($storeDefaults['returnsSummary'] ?? null),
                'windowDays' => $product->returns_window_days === null
                    ? null
                    : (int) $product->returns_window_days,
                'warrantyText' => $product->warranty_text,
            ],
            'faqs' => $product->faqs->map(static fn (ProductFaq $faq): array => [
                'id' => (int) $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sortOrder' => (int) $faq->sort_order,
            ])->values()->all(),
            'categories' => $product->categories
                ->map(static fn (Category $c): array => CategoryResource::ref($c))
                ->values()
                ->all(),
            'breadcrumbs' => self::breadcrumbs($product),
            'seo' => [
                'title' => $product->seo_title,
                'description' => $product->seo_description,
                'canonicalPath' => '/product/'.$product->slug,
                'ogImageUrl' => self::primaryMedia($product)?->url(),
                'noindex' => (bool) $product->seo_noindex,
            ],
            'shippingDimensions' => [
                'weightGrams' => $product->weight_grams === null ? null : (int) $product->weight_grams,
                'lengthMm' => $product->length_mm === null ? null : (int) $product->length_mm,
                'widthMm' => $product->width_mm === null ? null : (int) $product->width_mm,
                'heightMm' => $product->height_mm === null ? null : (int) $product->height_mm,
            ],
            'publishedAt' => $product->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function media(Product $product): array
    {
        return $product->media
            ->map(static fn (Media $media): array => MediaResource::make(
                $media,
                (int) ($media->pivot->sort_order ?? 0)
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function options(Product $product): array
    {
        return $product->options->map(static fn (ProductOption $option): array => [
            'id' => (int) $option->id,
            'name' => $option->name,
            'slug' => $option->slug,
            'values' => $option->values->map(static fn (ProductOptionValue $value): array => [
                'id' => (int) $value->id,
                'label' => $value->label,
                'value' => $value->value,
                'swatchHex' => $value->swatch_hex,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * A variant is identified by the SET of its optionValueIds. The frontend
     * matches on that set; it must never synthesize combinations.
     *
     * @return list<array<string, mixed>>
     */
    private static function variants(Product $product, AvailabilityResolver $availability): array
    {
        return $product->variants
            ->where('is_active', true)
            ->map(static function (ProductVariant $variant) use ($availability): array {
                $compare = $variant->compare_at_price;
                $price = $variant->price;

                return [
                    'id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'optionValueIds' => $variant->optionValues
                        ->map(static fn (ProductOptionValue $value): int => (int) $value->id)
                        ->values()
                        ->all(),
                    'price' => MoneyResource::make($price),
                    'compareAtPrice' => $compare !== null && $compare->greaterThan($price)
                        ? MoneyResource::make($compare)
                        : null,
                    'availability' => $availability->forVariant($variant),
                    'mediaId' => $variant->media_id === null ? null : (int) $variant->media_id,
                    'weightGrams' => $variant->weight_grams === null ? null : (int) $variant->weight_grams,
                    'isDefault' => (bool) $variant->is_default,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function specificationGroups(Product $product): array
    {
        $groups = [];

        foreach ($product->specifications as $spec) {
            /** @var ProductSpecification $spec */
            $key = $spec->group ?? '__ungrouped__';

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'group' => $spec->group,
                    'sortOrder' => (int) $spec->sort_order,
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = [
                'label' => $spec->label,
                'value' => $spec->value,
                'unit' => $spec->unit,
            ];
        }

        $groups = array_values($groups);

        usort($groups, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $groups;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function breadcrumbs(Product $product): array
    {
        $trail = [['label' => 'Shop', 'href' => '/shop']];

        $category = $product->categories->first();

        if ($category instanceof Category) {
            foreach ($category->ancestors() as $ancestor) {
                $trail[] = ['label' => $ancestor->name, 'href' => '/category/'.$ancestor->slug];
            }

            $trail[] = ['label' => $category->name, 'href' => '/category/'.$category->slug];
        }

        return $trail;
    }

    private static function primaryMedia(Product $product): ?Media
    {
        if ($product->primary_media_id !== null && $product->primaryMedia !== null) {
            return $product->primaryMedia;
        }

        return $product->media->first();
    }
}
