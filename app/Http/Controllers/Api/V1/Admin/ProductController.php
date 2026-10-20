<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ProductMediaRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends ApiController
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['id', 'desc'],
        'oldest' => ['id', 'asc'],
        'name_asc' => ['name', 'asc'],
        'name_desc' => ['name', 'desc'],
        'price_asc' => ['price_amount', 'asc'],
        'price_desc' => ['price_amount', 'desc'],
        'stock_asc' => ['stock_quantity', 'asc'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $this->validateQuery($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'category' => ['sometimes', 'integer', 'min:1'],
            'trashed' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ], ['sort.in' => 'The selected sort option is not supported.']);

        $query = Product::query()->with(['primaryMedia', 'variants'])->withCount('variants');

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->query('category')) {
            $query->whereHas('categories', static fn ($q) => $q->where('categories.id', (int) $categoryId));
        }

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('brand', 'like', $like);
            });
        }

        [$column, $direction] = self::SORTS[(string) $request->query('sort', 'newest')] ?? self::SORTS['newest'];

        return $this->paginated(
            $query->orderBy($column, $direction)->paginate($this->perPage($request)),
            static fn (Product $product): array => AdminProductResource::summary($product)
        );
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request): Product {
            /** @var Product $product */
            $product = Product::query()->create($this->attributes($request, null));

            $this->syncRelations($product, $request);

            return $product;
        });

        $this->audit->log(
            'product.created',
            $request->user()?->id,
            $product,
            array_keys($request->validated()),
            $request
        );

        return $this->data(AdminProductResource::detail($this->loadDetail($product)), 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('view', $product);

        return $this->data(AdminProductResource::detail($this->loadDetail($product)));
    }

    public function update(ProductRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        DB::transaction(function () use ($product, $request): void {
            $product->fill($this->attributes($request, $product))->save();

            $this->syncRelations($product, $request);
        });

        $this->audit->log(
            'product.updated',
            $request->user()?->id,
            $product,
            array_keys($request->validated()),
            $request
        );

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('delete', $product);

        // Soft delete: order item snapshots keep their own copy of the data.
        $product->delete();

        $this->audit->log('product.deleted', $request->user()?->id, $product, ['deleted_at'], $request);

        return response()->json(null, 204);
    }

    public function attachMedia(ProductMediaRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('mediaIds'))));

        DB::transaction(function () use ($product, $ids, $request): void {
            $payload = [];

            foreach ($ids as $index => $mediaId) {
                $payload[$mediaId] = ['sort_order' => $index];
            }

            $product->media()->syncWithoutDetaching($payload);

            $primary = $request->input('primaryMediaId');

            if ($primary !== null) {
                $product->forceFill(['primary_media_id' => (int) $primary])->save();
            } elseif ($product->primary_media_id === null && $ids !== []) {
                $product->forceFill(['primary_media_id' => $ids[0]])->save();
            }
        });

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    public function reorderMedia(ProductMediaRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        $ids = array_values(array_map('intval', (array) $request->input('mediaIds')));

        DB::transaction(function () use ($product, $ids): void {
            foreach ($ids as $index => $mediaId) {
                $product->media()->updateExistingPivot($mediaId, ['sort_order' => $index]);
            }
        });

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ProductRequest $request, ?Product $existing): array
    {
        $input = $request->validated();
        $out = [];

        $direct = [
            'name' => 'name',
            'sku' => 'sku',
            'brand' => 'brand',
            'status' => 'status',
            'priceAmount' => 'price_amount',
            'compareAtAmount' => 'compare_at_amount',
            'costAmount' => 'cost_amount',
            'currency' => 'currency',
            'inventoryPolicy' => 'inventory_policy',
            'stockQuantity' => 'stock_quantity',
            'lowStockThreshold' => 'low_stock_threshold',
            'weightGrams' => 'weight_grams',
            'lengthMm' => 'length_mm',
            'widthMm' => 'width_mm',
            'heightMm' => 'height_mm',
            'returnsWindowDays' => 'returns_window_days',
            'primaryMediaId' => 'primary_media_id',
            'seoNoindex' => 'seo_noindex',
            'publishedAt' => 'published_at',
        ];

        foreach ($direct as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = $input[$inputKey];
            }
        }

        $plain = [
            'shortDescription' => 'short_description',
            'shippingSummary' => 'shipping_summary',
            'returnsSummary' => 'returns_summary',
            'warrantyText' => 'warranty_text',
            'seoTitle' => 'seo_title',
            'seoDescription' => 'seo_description',
        ];

        foreach ($plain as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = Html::text($input[$inputKey], 2000);
            }
        }

        // Admin-authored rich text is sanitized before storage.
        if (array_key_exists('description', $input)) {
            $out['description'] = Html::sanitize($input['description']);
        }

        foreach (['valueProps' => 'value_props', 'whatsIncluded' => 'whats_included', 'badges' => 'badges'] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = $input[$inputKey] === null ? null : array_values((array) $input[$inputKey]);
            }
        }

        if (array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== '') {
            $out['slug'] = $input['slug'];
        } elseif ($existing === null) {
            $out['slug'] = $this->uniqueSlug((string) ($input['name'] ?? 'product'));
        }

        // Publishing stamps published_at when the admin did not supply one.
        if (($out['status'] ?? null) === Product::STATUS_PUBLISHED
            && ! array_key_exists('published_at', $out)
            && ($existing === null || $existing->published_at === null)) {
            $out['published_at'] = now();
        }

        return $out;
    }

    private function syncRelations(Product $product, ProductRequest $request): void
    {
        $input = $request->validated();

        if (array_key_exists('categoryIds', $input)) {
            $product->categories()->sync(array_map('intval', (array) $input['categoryIds']));
        }

        if (array_key_exists('mediaIds', $input)) {
            $payload = [];

            foreach (array_values(array_map('intval', (array) $input['mediaIds'])) as $index => $mediaId) {
                $payload[$mediaId] = ['sort_order' => $index];
            }

            $product->media()->sync($payload);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function find(int $id): Product
    {
        /** @var Product|null $product */
        $product = Product::withTrashed()->find($id);

        abort_if($product === null, 404);

        return $product;
    }

    private function loadDetail(Product $product): Product
    {
        return $product->load([
            'primaryMedia', 'media', 'categories', 'options.values',
            'variants.optionValues', 'variants.product',
            'specifications', 'faqs', 'relatedProducts',
        ]);
    }
}
