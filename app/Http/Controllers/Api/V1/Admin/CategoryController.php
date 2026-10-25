<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with('image')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->data(
            $categories->map(static fn (Category $category): array => CategoryResource::make($category) + [
                // The admin form edits the image by id; the public resource only
                // carries the resolved URL, which a picker cannot pre-select on.
                'imageMediaId' => $category->image_media_id === null ? null : (int) $category->image_media_id,
                'isActive' => (bool) $category->is_active,
                'productCount' => (int) ($category->products_count ?? 0),
                'seoTitle' => $category->seo_title,
                'seoDescription' => $category->seo_description,
            ])->values()->all()
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::query()->create($this->attributes($request, null));

        $this->audit->log('category.created', $request->user()?->id, $category, array_keys($request->validated()), $request);

        return $this->data(CategoryResource::detail($category->load('image')), 201);
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->find($id);

        $this->authorize('update', $category);

        $attributes = $this->attributes($request, $category);

        if (array_key_exists('parent_id', $attributes)) {
            $this->assertNoCycle($category, $attributes['parent_id'] === null ? null : (int) $attributes['parent_id']);
        }

        $category->fill($attributes)->save();

        $this->audit->log('category.updated', $request->user()?->id, $category, array_keys($attributes), $request);

        return $this->data(CategoryResource::detail($category->refresh()->load('image')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = $this->find($id);

        $this->authorize('delete', $category);

        $category->delete();

        $this->audit->log('category.deleted', $request->user()?->id, $category, ['deleted_at'], $request);

        return response()->json(null, 204);
    }

    /**
     * A category may not become its own ancestor.
     */
    private function assertNoCycle(Category $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === (int) $category->id) {
            throw ApiException::validation([
                'parentId' => ['A category cannot be its own parent.'],
            ]);
        }

        $cursor = Category::query()->find($parentId);
        $guard = 0;

        while ($cursor !== null && $guard < 20) {
            if ((int) $cursor->id === (int) $category->id) {
                throw ApiException::validation([
                    'parentId' => ['That parent would create a cycle in the category tree.'],
                ]);
            }

            $cursor = $cursor->parent;
            $guard++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CategoryRequest $request, ?Category $existing): array
    {
        $input = $request->validated();
        $out = [];

        if (array_key_exists('name', $input)) {
            $out['name'] = Html::text($input['name'], 160);
        }

        if (array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== '') {
            $out['slug'] = $input['slug'];
        } elseif ($existing === null) {
            $out['slug'] = $this->uniqueSlug((string) ($input['name'] ?? 'category'));
        }

        foreach ([
            'parentId' => 'parent_id',
            'imageMediaId' => 'image_media_id',
            'isActive' => 'is_active',
            'sortOrder' => 'sort_order',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = $input[$inputKey];
            }
        }

        foreach ([
            'description' => 'description',
            'seoTitle' => 'seo_title',
            'seoDescription' => 'seo_description',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = Html::text($input[$inputKey], 2000);
            }
        }

        return $out;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function find(int $id): Category
    {
        /** @var Category|null $category */
        $category = Category::query()->find($id);

        abort_if($category === null, 404);

        return $category;
    }
}
