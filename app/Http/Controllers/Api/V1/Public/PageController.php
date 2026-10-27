<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends ApiController
{
    /**
     * Published pages, without their bodies.
     *
     * The storefront needs this to link the CMS pages it was not told about:
     * `navigation.footer` is hand-curated, so a page an administrator creates
     * is otherwise reachable only by typing its URL. Ordered by title so the
     * list is stable, and `seo_noindex` pages are left out - a page the store
     * asked search engines to ignore has no business in site-wide navigation.
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        $pages = Page::query()
            ->published()
            ->where('seo_noindex', false)
            ->get(['id', 'slug', 'title', 'excerpt', 'translations', 'updated_at']);

        return $this->data(
            $pages->map(static fn (Page $page): array => [
                'id' => (int) $page->id,
                'slug' => $page->slug,
                'title' => (string) $page->localized('title', $locale),
                'excerpt' => $page->localized('excerpt', $locale),
                'updatedAt' => $page->updated_at?->toIso8601String(),
            ])->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values()->all()
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        /** @var Page|null $page */
        $page = Page::query()->published()->where('slug', $slug)->first();

        abort_if($page === null, 404);

        return $this->data(PageResource::make($page, $this->locale($request)));
    }

    /**
     * ?locale=nl asks for a translation. Anything else, or a field the page
     * has no translation for, gets the default-language content.
     */
    private function locale(Request $request): ?string
    {
        $locale = strtolower((string) $request->query('locale', ''));

        return in_array($locale, Page::TRANSLATION_LOCALES, true) ? $locale : null;
    }
}
