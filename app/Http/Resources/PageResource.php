<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Page;

final class PageResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Page $page): array
    {
        return [
            'id' => (int) $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'body' => $page->body,
            'excerpt' => $page->excerpt,
            'seo' => [
                'title' => $page->seo_title,
                'description' => $page->seo_description,
                'noindex' => (bool) $page->seo_noindex,
            ],
            'updatedAt' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function admin(Page $page): array
    {
        return self::make($page) + [
            'isPublished' => (bool) $page->is_published,
            'publishedAt' => $page->published_at?->toIso8601String(),
            'createdAt' => $page->created_at?->toIso8601String(),
        ];
    }
}
