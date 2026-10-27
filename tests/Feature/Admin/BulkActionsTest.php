<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Media;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Bulk delete for the catalog, media and orders, plus single order delete and
 * bulk order status updates.
 */
class BulkActionsTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
    }

    private function order(string $status = 'pending'): Order
    {
        return Order::query()->create([
            'order_number' => 'HK-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'access_token' => Str::random(48),
            'email' => 'buyer@example.test',
            'currency' => 'USD',
            'total_amount' => 5799,
            'status' => $status,
            'payment_status' => 'unpaid',
            'shipping_address' => ['line1' => '1 Example St', 'city' => 'Portland', 'countryCode' => 'US'],
            'billing_address' => ['line1' => '1 Example St', 'city' => 'Portland', 'countryCode' => 'US'],
        ]);
    }

    public function test_products_are_bulk_soft_deleted(): void
    {
        $this->actingAsAdmin();
        $keep = $this->publishedProduct();
        $a = $this->publishedProduct();
        $b = $this->publishedProduct();

        $this->postJson('/api/v1/admin/products/bulk-delete', ['ids' => [$a->id, $b->id, 999999]])
            ->assertOk()
            ->assertJsonPath('data.succeeded', [$a->id, $b->id]);

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($keep);
    }

    public function test_categories_are_bulk_deleted(): void
    {
        $this->actingAsAdmin();
        $categories = Category::factory()->count(2)->create();

        $this->postJson('/api/v1/admin/categories/bulk-delete', ['ids' => $categories->pluck('id')->all()])
            ->assertOk();

        $this->assertSame(0, Category::query()->count());
    }

    public function test_media_is_bulk_deleted_with_its_files(): void
    {
        Storage::fake('uploads');
        $this->actingAsAdmin();
        $media = Media::factory()->count(2)->create();
        Storage::disk('uploads')->put($media[0]->path, 'x');

        $this->postJson('/api/v1/admin/media/bulk-delete', ['ids' => $media->pluck('id')->all()])
            ->assertOk();

        $this->assertSame(0, Media::query()->count());
        Storage::disk('uploads')->assertMissing($media[0]->path);
    }

    public function test_an_order_can_be_deleted(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        $this->deleteJson('/api/v1/admin/orders/'.$order->id)->assertNoContent();

        $this->assertSoftDeleted($order);
    }

    public function test_orders_are_bulk_deleted(): void
    {
        $this->actingAsAdmin();
        $a = $this->order();
        $b = $this->order('delivered');

        $this->postJson('/api/v1/admin/orders/bulk-delete', ['ids' => [$a->id, $b->id]])->assertOk();

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
    }

    public function test_bulk_status_reports_orders_that_cannot_make_the_move(): void
    {
        $this->actingAsAdmin();
        $pending = $this->order('pending');
        $delivered = $this->order('delivered');

        $this->patchJson('/api/v1/admin/orders/bulk-status', [
            'ids' => [$pending->id, $delivered->id],
            'status' => 'processing',
        ])
            ->assertOk()
            ->assertJsonPath('data.succeeded', [$pending->id])
            ->assertJsonPath('data.failed.0.id', $delivered->id);

        $this->assertSame('processing', $pending->refresh()->status);
        $this->assertSame('delivered', $delivered->refresh()->status);
    }

    public function test_bulk_endpoints_validate_the_id_list(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/products/bulk-delete', ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');
    }

    public function test_a_customer_cannot_bulk_delete(): void
    {
        $product = $this->publishedProduct();
        Sanctum::actingAs($this->customerUser(), ['customer']);

        $this->postJson('/api/v1/admin/products/bulk-delete', ['ids' => [$product->id]])->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }
}
