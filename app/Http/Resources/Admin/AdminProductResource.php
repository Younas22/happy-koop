<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\MoneyResource;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;

/**
 * Admin product payload. This is the ONLY resource that exposes
 * `costPrice` — it is served exclusively behind the admin middleware stack
 * and an authorized Policy check.
 */
final class AdminProductResource
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'status' => $product->status,
            'price' => MoneyResource::make($product->price),
            'compareAtPrice' => MoneyResource::nullable($product->compare_at_price),
            'costPrice' => MoneyResource::nullable($product->cost_price),
            'stockQuantity' => (int) $product->stock_quantity,
            'inventoryPolicy' => $product->inventory_policy,
            'variantCount' => (int) ($product->variants_count ?? $product->variants->count()),
            'primaryImage' => MediaResource::imageRef($product->primaryMedia),
            'publishedAt' => $product->published_at?->toIso8601String(),
            'updatedAt' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'status' => $product->status,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'price' => MoneyResource::make($product->price),
            'compareAtPrice' => MoneyResource::nullable($product->compare_at_price),
            'costPrice' => MoneyResource::nullable($product->cost_price),
            'currency' => $product->currency,
            'inventoryPolicy' => $product->inventory_policy,
            'stockQuantity' => (int) $product->stock_quantity,
            'lowStockThreshold' => $product->low_stock_threshold === null
                ? null
                : (int) $product->low_stock_threshold,
            'weightGrams' => $product->weight_grams === null ? null : (int) $product->weight_grams,
            'lengthMm' => $product->length_mm === null ? null : (int) $product->length_mm,
            'widthMm' => $product->width_mm === null ? null : (int) $product->width_mm,
            'heightMm' => $product->height_mm === null ? null : (int) $product->height_mm,
            'valueProps' => array_values((array) ($product->value_props ?? [])),
            'whatsIncluded' => array_values((array) ($product->whats_included ?? [])),
            'shippingSummary' => $product->shipping_summary,
            'returnsSummary' => $product->returns_summary,
            'warrantyText' => $product->warranty_text,
            'returnsWindowDays' => $product->returns_window_days === null
                ? null
                : (int) $product->returns_window_days,
            'badges' => array_values((array) ($product->badges ?? [])),
            'primaryMediaId' => $product->primary_media_id === null ? null : (int) $product->primary_media_id,
            'seo' => [
                'title' => $product->seo_title,
                'description' => $product->seo_description,
                'noindex' => (bool) $product->seo_noindex,
            ],
            'categoryIds' => $product->categories->map(static fn (Category $c): int => (int) $c->id)->values()->all(),
            'categories' => $product->categories
                ->map(static fn (Category $c): array => CategoryResource::ref($c))->values()->all(),
            'media' => $product->media->map(static fn (Media $m): array => MediaResource::make(
                $m,
                (int) ($m->pivot->sort_order ?? 0)
            ))->values()->all(),
            'options' => $product->options->map(static fn ($option): array => [
                'id' => (int) $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'sortOrder' => (int) $option->sort_order,
                'values' => $option->values->map(static fn ($value): array => [
                    'id' => (int) $value->id,
                    'label' => $value->label,
                    'value' => $value->value,
                    'swatchHex' => $value->swatch_hex,
                    'sortOrder' => (int) $value->sort_order,
                ])->values()->all(),
            ])->values()->all(),
            'variants' => $product->variants->map(static fn (ProductVariant $variant): array => [
                'id' => (int) $variant->id,
                'sku' => $variant->sku,
                'priceAmount' => $variant->price_amount === null ? null : (int) $variant->price_amount,
                'compareAtAmount' => $variant->compare_at_amount === null
                    ? null
                    : (int) $variant->compare_at_amount,
                'stockQuantity' => (int) $variant->stock_quantity,
                'inventoryPolicy' => $variant->inventory_policy,
                'weightGrams' => $variant->weight_grams === null ? null : (int) $variant->weight_grams,
                'mediaId' => $variant->media_id === null ? null : (int) $variant->media_id,
                'optionValueIds' => $variant->optionValues->map(static fn ($v): int => (int) $v->id)
                    ->values()->all(),
                'isDefault' => (bool) $variant->is_default,
                'isActive' => (bool) $variant->is_active,
                'sortOrder' => (int) $variant->sort_order,
            ])->values()->all(),
            'specifications' => $product->specifications
                ->map(static fn (ProductSpecification $spec): array => [
                    'id' => (int) $spec->id,
                    'group' => $spec->group,
                    'label' => $spec->label,
                    'value' => $spec->value,
                    'unit' => $spec->unit,
                    'sortOrder' => (int) $spec->sort_order,
                ])->values()->all(),
            'faqs' => $product->faqs->map(static fn (ProductFaq $faq): array => [
                'id' => (int) $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sortOrder' => (int) $faq->sort_order,
            ])->values()->all(),
            'relatedProductIds' => $product->relatedProducts
                ->map(static fn (Product $p): int => (int) $p->id)->values()->all(),
            'publishedAt' => $product->published_at?->toIso8601String(),
            'createdAt' => $product->created_at?->toIso8601String(),
            'updatedAt' => $product->updated_at?->toIso8601String(),
            'deletedAt' => $product->deleted_at?->toIso8601String(),
        ];
    }
}
