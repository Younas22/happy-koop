<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Page;

final class PageResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Page $page, ?string $locale = null): array
    {
        return [
            'id' => (int) $page->id,
            'slug' => $page->slug,
            'title' => (string) $page->localized('title', $locale),
            'body' => $page->localized('body', $locale),
            'excerpt' => $page->localized('excerpt', $locale),
            'seo' => [
                'title' => $page->localized('seo_title', $locale),
                'description' => $page->localized('seo_description', $locale),
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
        $translations = [];

        foreach (Page::TRANSLATION_LOCALES as $locale) {
            $stored = (array) ($page->translations[$locale] ?? []);
            $translations[$locale] = [
                'title' => $stored['title'] ?? null,
                'body' => $stored['body'] ?? null,
                'excerpt' => $stored['excerpt'] ?? null,
                'seoTitle' => $stored['seo_title'] ?? null,
                'seoDescription' => $stored['seo_description'] ?? null,
            ];
        }

        return self::make($page) + [
            'translations' => $translations,
            'isPublished' => (bool) $page->is_published,
            'publishedAt' => $page->published_at?->toIso8601String(),
            'createdAt' => $page->created_at?->toIso8601String(),
        ];
    }
}
