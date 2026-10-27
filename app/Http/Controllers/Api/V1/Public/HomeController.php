<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\RatingResolver;
use Illuminate\Http\JsonResponse;

class HomeController extends ApiController
{
    public function __construct(
        private readonly CatalogContext $context,
        private readonly RatingResolver $ratings,
    ) {}

    /**
     * Admin-composed homepage (api-contract.md 4). `hero` may be null; a
     * section whose referenced content no longer exists resolves to an empty
     * list rather than fabricated filler.
     */
    public function index(): JsonResponse
    {
        $sections = HomeSection::query()->active()->orderBy('sort_order')->orderBy('id')->get();

        $hero = null;
        $out = [];

        foreach ($sections as $section) {
            $config = (array) ($section->config ?? []);

            if ($section->type === HomeSection::TYPE_HERO) {
                if ($hero === null) {
                    $imageUrls = $this->heroImageUrls($config);
                    $hero = [
                        'headline' => $config['headline'] ?? $section->heading,
                        'subheadline' => $config['subheadline'] ?? null,
                        // `imageUrl` stays for older clients: the first slide.
                        'imageUrl' => $imageUrls[0] ?? null,
                        'imageUrls' => $imageUrls,
                        'ctaLabel' => $config['ctaLabel'] ?? null,
                        'ctaHref' => $config['ctaHref'] ?? null,
                    ];
                }

                continue;
            }

            $out[] = match ($section->type) {
                HomeSection::TYPE_CATEGORY_TILES => [
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'categories' => $this->categories($config),
                ],
                HomeSection::TYPE_PRODUCT_GRID => [
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'products' => $this->products($config),
                ],
                HomeSection::TYPE_BENEFITS => [
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'items' => array_values((array) ($config['items'] ?? [])),
                ],
                HomeSection::TYPE_RICH_TEXT => [
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'html' => $config['html'] ?? null,
                ],
                default => [
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'config' => $config,
                ],
            };
        }

        return $this->data([
            'hero' => $hero,
            'sections' => $out,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function categories(array $config): array
    {
        $ids = array_map('intval', (array) ($config['categoryIds'] ?? []));

        if ($ids === []) {
            return [];
        }

        $categories = Category::query()->active()->with('image')->whereIn('id', $ids)->get()
            ->sortBy(static fn (Category $c): int => array_search((int) $c->id, $ids, true) ?: 0);

        return $categories->map(static fn (Category $c): array => CategoryResource::make($c))->values()->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function products(array $config): array
    {
        $ids = array_map('intval', (array) ($config['productIds'] ?? []));
        $limit = max(1, min(24, (int) ($config['limit'] ?? 8)));

        $query = Product::query()
            ->published()
            ->with(['primaryMedia', 'media', 'categories'])
            ->withCount('variants');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $products = $query->orderByDesc('published_at')->limit($limit)->get();

        $ratings = $this->ratings->forProducts(
            $products->map(static fn (Product $p): int => (int) $p->id)->all()
        );
        $availability = $this->context->availability();

        return $products->map(static fn (Product $p): array => ProductCardResource::make(
            $p,
            $availability,
            $ratings[(int) $p->id] ?? null
        ))->values()->all();
    }

    /**
     * The hero's slides, in the admin's order. `imageMediaIds` replaced the
     * single `imageMediaId`, which is still read for sections saved before.
     * Deleted media is skipped.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function heroImageUrls(array $config): array
    {
        $ids = array_key_exists('imageMediaIds', $config)
            ? (array) $config['imageMediaIds']
            : [$config['imageMediaId'] ?? null];

        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));

        if ($ids === []) {
            return [];
        }

        $media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(
            static fn (int $id): ?string => $media->get($id)?->url(),
            $ids
        )));
    }
}
