<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;

final class CategoryResource
{
    /**
     * @param  array<int, int>  $productCounts  category id => published product count
     * @return array<string, mixed>
     */
    public static function make(Category $category, array $productCounts = [], bool $withChildren = false): array
    {
        $payload = [
            'id' => (int) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parentId' => $category->parent_id === null ? null : (int) $category->parent_id,
            'description' => $category->description,
            'imageUrl' => $category->image?->url(),
            'productCount' => (int) ($productCounts[(int) $category->id] ?? 0),
            'sortOrder' => (int) $category->sort_order,
            'children' => [],
        ];

        if ($withChildren && $category->relationLoaded('children')) {
            $payload['children'] = $category->children
                ->map(static fn (Category $child): array => self::make($child, $productCounts, true))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * Detail view: adds SEO and the breadcrumb trail (root first).
     *
     * @param  array<int, int>  $productCounts
     * @return array<string, mixed>
     */
    public static function detail(Category $category, array $productCounts = []): array
    {
        return self::make($category, $productCounts, true) + [
            'seo' => [
                'title' => $category->seo_title,
                'description' => $category->seo_description,
                'canonicalPath' => '/category/'.$category->slug,
                'noindex' => false,
            ],
            'ancestors' => array_map(
                static fn (Category $ancestor): array => [
                    'id' => (int) $ancestor->id,
                    'name' => $ancestor->name,
                    'slug' => $ancestor->slug,
                ],
                $category->ancestors()
            ),
        ];
    }

    /**
     * Minimal reference embedded in product payloads.
     *
     * @return array<string, mixed>
     */
    public static function ref(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }
}
