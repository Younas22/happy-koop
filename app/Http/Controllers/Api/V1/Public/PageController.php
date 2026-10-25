<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

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
    public function index(): JsonResponse
    {
        $pages = Page::query()
            ->published()
            ->where('seo_noindex', false)
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'excerpt', 'updated_at']);

        return $this->data(
            $pages->map(static fn (Page $page): array => [
                'id' => (int) $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'updatedAt' => $page->updated_at?->toIso8601String(),
            ])->all()
        );
    }

    public function show(string $slug): JsonResponse
    {
        /** @var Page|null $page */
        $page = Page::query()->published()->where('slug', $slug)->first();

        abort_if($page === null, 404);

        return $this->data(PageResource::make($page));
    }
}
