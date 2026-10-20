<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\RatingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    /**
     * Sort values are validated against this allowlist; anything else is a
     * 422 (api-contract.md 1.8 / 4).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['published_at', 'desc'],
        'price_asc' => ['price_amount', 'asc'],
        'price_desc' => ['price_amount', 'desc'],
        'name_asc' => ['name', 'asc'],
        'name_desc' => ['name', 'desc'],
        'relevance' => ['published_at', 'desc'],
    ];

    public function __construct(
        private readonly CatalogContext $context,
        private readonly RatingResolver $ratings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->validateQuery($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:160'],
            'min_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'in_stock' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ], [
            'sort.in' => 'The selected sort option is not supported.',
        ]);

        $term = trim((string) $request->query('q', ''));

        $query = Product::query()
            ->published()
            ->with(['primaryMedia', 'media', 'categories'])
            ->withCount('variants');

        if ($term !== '') {
            // MariaDB 10.4: a normal index plus LIKE, not a FULLTEXT index.
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(static function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('brand', 'like', $like);
            });
        }

        if ($slug = $request->query('category')) {
            $category = Category::query()->active()->where('slug', $slug)->first();

            if ($category === null) {
                return $this->paginated(
                    Product::query()->whereRaw('1 = 0')->paginate($this->perPage($request)),
                    static fn ($item): array => []
                );
            }

            $ids = $category->descendantIds();

            $query->whereHas('categories', static fn (Builder $q) => $q->whereIn('categories.id', $ids));
        }

        if (($min = $request->query('min_price')) !== null && $min !== '') {
            $query->where('price_amount', '>=', (int) $min);
        }

        if (($max = $request->query('max_price')) !== null && $max !== '') {
            $query->where('price_amount', '<=', (int) $max);
        }

        if ($request->has('in_stock') && $request->boolean('in_stock')) {
            $query->where(static function (Builder $q): void {
                $q->where('stock_quantity', '>', 0)
                    ->orWhere('inventory_policy', Product::POLICY_CONTINUE)
                    ->orWhereHas('variants', static fn (Builder $v) => $v
                        ->where('is_active', true)
                        ->where('stock_quantity', '>', 0));
            });
        }

        $sortKey = (string) $request->query('sort', $term !== '' ? 'relevance' : 'newest');
        [$column, $direction] = self::SORTS[$sortKey] ?? self::SORTS['newest'];

        if ($sortKey === 'relevance' && $term !== '') {
            $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$term.'%']);
        }

        $query->orderBy($column, $direction)->orderBy('id', 'desc');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        $ratings = $this->ratings->forProducts(
            array_map(static fn (Product $p): int => (int) $p->id, $paginator->items())
        );

        $availability = $this->context->availability();

        return $this->paginated(
            $paginator,
            static fn (Product $product): array => ProductCardResource::make(
                $product,
                $availability,
                $ratings[(int) $product->id] ?? null
            )
        );
    }

    public function show(string $slug): JsonResponse
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->published()
            ->with([
                'primaryMedia',
                'media',
                'categories.parent',
                'options.values',
                'variants.optionValues',
                'variants.product',
                'specifications',
                'faqs',
            ])
            ->where('slug', $slug)
            ->first();

        abort_if($product === null, 404);

        return $this->data(ProductDetailResource::make(
            $product,
            $this->context->availability(),
            $this->ratings->forProduct((int) $product->id),
            $this->context->storeDefaults()
        ));
    }

    /**
     * Related products: explicit admin-curated relations first, then shared
     * category and recency. Never padded with unrelated filler.
     */
    public function related(Request $request, string $slug): JsonResponse
    {
        $this->validateQuery($request, [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $limit = (int) $request->query('limit', '4');

        /** @var Product|null $product */
        $product = Product::query()->published()->with('categories')->where('slug', $slug)->first();

        abort_if($product === null, 404);

        $curated = $product->relatedProducts()
            ->published()
            ->with(['primaryMedia', 'media', 'categories'])
            ->withCount('variants')
            ->limit($limit)
            ->get();

        $results = $curated->keyBy('id');

        if ($results->count() < $limit) {
            $categoryIds = $product->categories->pluck('id')->all();

            if ($categoryIds !== []) {
                $siblings = Product::query()
                    ->published()
                    ->with(['primaryMedia', 'media', 'categories'])
                    ->withCount('variants')
                    ->whereKeyNot($product->id)
                    ->whereKeyNot($results->keys()->all() ?: [0])
                    ->whereHas('categories', static fn (Builder $q) => $q->whereIn('categories.id', $categoryIds))
                    ->orderByDesc('published_at')
                    ->limit($limit - $results->count())
                    ->get();

                foreach ($siblings as $sibling) {
                    $results->put($sibling->id, $sibling);
                }
            }
        }

        $ratings = $this->ratings->forProducts($results->keys()->map(static fn ($id): int => (int) $id)->all());
        $availability = $this->context->availability();

        return $this->data(
            $results->take($limit)
                ->map(static fn (Product $p): array => ProductCardResource::make(
                    $p,
                    $availability,
                    $ratings[(int) $p->id] ?? null
                ))
                ->values()
                ->all()
        );
    }
}
