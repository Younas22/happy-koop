<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ProductFaqsRequest;
use App\Http\Requests\Admin\ProductRelatedRequest;
use App\Http\Requests\Admin\ProductSpecificationsRequest;
use App\Http\Requests\Admin\ProductVariantsRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Atomic replacement of a product's option/variant set, specifications, FAQs
 * and related products (api-contract.md 8).
 */
class ProductRelationsController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function replaceVariants(ProductVariantsRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        DB::transaction(function () use ($product, $request): void {
            // Variants referenced by an order item are never deleted: the
            // snapshot keeps its own copy, but the FK is nullOnDelete and we
            // prefer deactivating over destroying history.
            $referenced = DB::table('order_items')
                ->whereNotNull('product_variant_id')
                ->whereIn('product_variant_id', $product->variants()->pluck('id'))
                ->pluck('product_variant_id')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $referenced)
                ->update(['is_active' => false]);

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereNotIn('id', $referenced ?: [0])
                ->delete();

            $product->options()->delete();

            $valueIds = [];

            foreach ((array) $request->input('options', []) as $optionIndex => $optionInput) {
                $option = $product->options()->create([
                    'name' => (string) $optionInput['name'],
                    'slug' => (string) $optionInput['slug'],
                    'sort_order' => $optionIndex,
                ]);

                foreach ((array) ($optionInput['values'] ?? []) as $valueIndex => $valueInput) {
                    $value = $option->values()->create([
                        'label' => (string) $valueInput['label'],
                        'value' => (string) $valueInput['value'],
                        'swatch_hex' => $valueInput['swatchHex'] ?? null,
                        'sort_order' => $valueIndex,
                    ]);

                    $valueIds[$optionInput['slug'].':'.$valueInput['value']] = (int) $value->id;
                    $valueIds[(string) $valueInput['value']] = (int) $value->id;
                }
            }

            foreach ((array) $request->input('variants', []) as $index => $variantInput) {
                $variant = $product->variants()->create([
                    'sku' => $variantInput['sku'] ?? null,
                    'price_amount' => $variantInput['priceAmount'] ?? null,
                    'compare_at_amount' => $variantInput['compareAtAmount'] ?? null,
                    'stock_quantity' => (int) ($variantInput['stockQuantity'] ?? 0),
                    'inventory_policy' => $variantInput['inventoryPolicy'] ?? null,
                    'weight_grams' => $variantInput['weightGrams'] ?? null,
                    'media_id' => $variantInput['mediaId'] ?? null,
                    'is_default' => (bool) ($variantInput['isDefault'] ?? false),
                    'is_active' => (bool) ($variantInput['isActive'] ?? true),
                    'sort_order' => $index,
                ]);

                $ids = [];

                foreach ((array) ($variantInput['optionValues'] ?? []) as $reference) {
                    $resolved = $valueIds[(string) $reference] ?? null;

                    if ($resolved === null) {
                        throw ApiException::validation([
                            "variants.{$index}.optionValues" => [
                                'An option value in this variant does not exist in the submitted options.',
                            ],
                        ]);
                    }

                    $ids[] = $resolved;
                }

                $variant->optionValues()->sync(array_values(array_unique($ids)));
            }

            // Exactly one default when variants exist.
            $defaults = $product->variants()->where('is_default', true)->where('is_active', true)->count();

            if ($defaults !== 1) {
                $product->variants()->update(['is_default' => false]);

                $first = $product->variants()->where('is_active', true)->orderBy('sort_order')->first();

                $first?->forceFill(['is_default' => true])->save();
            }
        });

        $this->audit->log('product.variants.replaced', $request->user()?->id, $product, ['variants'], $request);

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    public function replaceSpecifications(ProductSpecificationsRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        DB::transaction(function () use ($product, $request): void {
            $product->specifications()->delete();

            foreach ((array) $request->input('specifications', []) as $index => $spec) {
                $product->specifications()->create([
                    'group' => Html::text($spec['group'] ?? null, 120),
                    'label' => (string) Html::text($spec['label'], 160),
                    'value' => (string) Html::text($spec['value'], 1000),
                    'unit' => Html::text($spec['unit'] ?? null, 32),
                    'sort_order' => $index,
                ]);
            }
        });

        $this->audit->log('product.specifications.replaced', $request->user()?->id, $product, ['specifications'], $request);

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    public function replaceFaqs(ProductFaqsRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        DB::transaction(function () use ($product, $request): void {
            $product->faqs()->delete();

            foreach ((array) $request->input('faqs', []) as $index => $faq) {
                $product->faqs()->create([
                    'question' => (string) Html::text($faq['question'], 500),
                    'answer' => (string) Html::sanitize($faq['answer']),
                    'sort_order' => $index,
                ]);
            }
        });

        $this->audit->log('product.faqs.replaced', $request->user()?->id, $product, ['faqs'], $request);

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
    }

    public function replaceRelated(ProductRelatedRequest $request, int $id): JsonResponse
    {
        $product = $this->find($id);

        $this->authorize('update', $product);

        $ids = array_values(array_filter(
            array_unique(array_map('intval', (array) $request->input('productIds'))),
            static fn (int $relatedId): bool => $relatedId !== (int) $product->id
        ));

        DB::transaction(function () use ($product, $ids): void {
            $payload = [];

            foreach ($ids as $index => $relatedId) {
                $payload[$relatedId] = ['sort_order' => $index];
            }

            $product->relatedProducts()->sync($payload);
        });

        $this->audit->log('product.related.replaced', $request->user()?->id, $product, ['related'], $request);

        return $this->data(AdminProductResource::detail($this->loadDetail($product->refresh())));
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
