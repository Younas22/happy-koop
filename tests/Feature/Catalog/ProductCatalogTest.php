<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    public function test_product_list_returns_the_contract_pagination_meta(): void
    {
        $this->seedStore();
        $this->publishedProduct();

        $this->getJson('/api/v1/products?per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'shortDescription', 'price', 'compareAtPrice', 'primaryImage', 'availability', 'rating', 'categories', 'hasVariants', 'badges']],
                'meta' => ['currentPage', 'perPage', 'total', 'lastPage', 'from', 'to'],
            ])
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.perPage', 1);
    }

    public function test_public_product_endpoints_never_expose_cost_or_supplier_data(): void
    {
        $this->seedStore();
        $product = $this->publishedProduct(['cost_amount' => 1234]);

        $forbidden = [
            'cost_amount', 'costAmount', 'costPrice', 'cost',
            'supplier', 'supplierId', 'wholesale', 'moq', 'margin', 'internalNote',
        ];

        foreach ([
            '/api/v1/products',
            '/api/v1/products/'.$product->slug,
            '/api/v1/products/'.$product->slug.'/related',
        ] as $url) {
            $raw = $this->getJson($url)->assertOk()->getContent();

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $raw,
                    "[{$url}] leaked a private field containing [{$needle}]"
                );
            }

            // The cost VALUE must not appear either.
            $this->assertStringNotContainsString('1234', $raw, "[{$url}] leaked the cost amount");
        }
    }

    public function test_rating_is_null_with_no_approved_reviews(): void
    {
        $this->seedStore();
        $product = $this->publishedProduct();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.rating', null);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.reviewCount', 0);

        // A pending review must not create a rating either.
        Review::query()->create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'body' => 'Pending review body.',
            'author_name' => 'Ada L.',
            'status' => Review::STATUS_PENDING,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.rating', null);
    }

    public function test_review_summary_is_null_at_zero_count(): void
    {
        $this->seedStore();
        $product = $this->publishedProduct();

        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')
            ->assertOk()
            ->assertJsonPath('summary', null)
            ->assertJsonPath('data', []);
    }

    public function test_invalid_sort_is_rejected_with_422(): void
    {
        $this->seedStore();

        $this->getJson('/api/v1/products?sort=cheapest')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['message', 'code', 'errors' => ['sort']]);
    }

    public function test_draft_products_are_not_public(): void
    {
        $this->seedStore();
        $draft = $this->publishedProduct();
        $draft->forceFill(['status' => 'draft', 'published_at' => null])->save();

        $this->getJson('/api/v1/products/'.$draft->slug)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_compare_at_price_is_null_unless_strictly_greater(): void
    {
        $this->seedStore();

        $equal = $this->publishedProduct(['price_amount' => 2900, 'compare_at_amount' => 2900]);
        $higher = $this->publishedProduct(['price_amount' => 2900, 'compare_at_amount' => 3500]);

        $this->getJson('/api/v1/products/'.$equal->slug)
            ->assertOk()
            ->assertJsonPath('data.compareAtPrice', null);

        $this->getJson('/api/v1/products/'.$higher->slug)
            ->assertOk()
            ->assertJsonPath('data.compareAtPrice.amount', 3500);
    }
}
