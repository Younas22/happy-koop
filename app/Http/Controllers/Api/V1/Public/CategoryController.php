<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->validateQuery($request, [
            'tree' => ['sometimes', 'boolean'],
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $tree = $request->boolean('tree');
        $activeOnly = ! $request->has('active_only') || $request->boolean('active_only');

        $query = Category::query()->with('image')->orderBy('sort_order')->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        $categories = $query->get();
        $counts = $this->publishedProductCounts();

        if (! $tree) {
            return $this->data(
                $categories->map(static fn (Category $category): array => CategoryResource::make($category, $counts))
                    ->values()
                    ->all()
            );
        }

        $byParent = $categories->groupBy(static fn (Category $c): string => (string) ($c->parent_id ?? 'root'));

        $attach = static function (Category $category) use (&$attach, $byParent): Category {
            $children = ($byParent[(string) $category->id] ?? collect())
                ->map(static fn (Category $child): Category => $attach($child))
                ->values();

            $category->setRelation('children', $children);

            return $category;
        };

        $roots = ($byParent['root'] ?? collect())
            ->map(static fn (Category $category): Category => $attach($category))
            ->map(static fn (Category $category): array => CategoryResource::make($category, $counts, true))
            ->values()
            ->all();

        return $this->data($roots);
    }

    public function show(string $slug): JsonResponse
    {
        /** @var Category|null $category */
        $category = Category::query()
            ->with(['image', 'children' => static fn ($q) => $q->where('is_active', true)])
            ->active()
            ->where('slug', $slug)
            ->first();

        abort_if($category === null, 404);

        return $this->data(CategoryResource::detail($category, $this->publishedProductCounts()));
    }

    /**
     * Published product counts per category, in one query.
     *
     * @return array<int, int>
     */
    private function publishedProductCounts(): array
    {
        return DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->where(static function ($query): void {
                $query->whereNull('products.published_at')
                    ->orWhere('products.published_at', '<=', now());
            })
            ->groupBy('category_product.category_id')
            ->selectRaw('category_product.category_id as category_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'category_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }
}
