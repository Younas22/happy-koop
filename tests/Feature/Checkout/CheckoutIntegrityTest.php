<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Checkout money integrity: the server is the only authority on price, and
 * a duplicate submission must not produce a duplicate order
 * (api-contract.md 1.5, 5).
 */
class CheckoutIntegrityTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function idempotencyKey(): string
    {
        return (string) Str::uuid();
    }

    public function test_cart_validate_ignores_a_client_supplied_price(): void
    {
        $this->seedStore();
        $product = $this->publishedProduct(['price_amount' => 2600, 'currency' => 'USD']);

        $response = $this->postJson('/api/v1/cart/validate', [
            'items' => [[
                'productId' => $product->id,
                'variantId' => null,
                'quantity' => 2,
                // Hostile extras. Both must be ignored outright.
                'unitPrice' => ['amount' => 1, 'currency' => 'USD'],
                'price' => 1,
            ]],
            'countryCode' => 'US',
        ])->assertOk();

        $response->assertJsonPath('data.items.0.unitPrice.amount', 2600)
            ->assertJsonPath('data.items.0.lineTotal.amount', 5200)
            ->assertJsonPath('data.totals.subtotal.amount', 5200);
    }

    public function test_shipping_and_tax_are_null_until_determined_not_zero(): void
    {
        $this->seedStore();
        $product = $this->publishedProduct();

        // No shipping method chosen yet, and no tax engine is configured.
        // Returning 0 here would read to a customer as "free", which is a
        // claim the store has not made.
        $this->postJson('/api/v1/cart/validate', [
            'items' => [['productId' => $product->id, 'variantId' => null, 'quantity' => 1]],
            'countryCode' => 'US',
        ])
            ->assertOk()
            ->assertJsonPath('data.totals.shipping', null)
            ->assertJsonPath('data.totals.tax', null);
    }

    public function test_checkout_rejects_a_stale_cart_hash(): void
    {
        $this->seedStore();
        $this->usShippingZone();
        $this->enableStripe();
        $product = $this->publishedProduct();

        $this->withHeader('Idempotency-Key', $this->idempotencyKey())
            ->postJson('/api/v1/checkout/initialize', $this->checkoutPayload($product, 'sha256:stale'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'CART_CHANGED');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_totals_are_computed_by_the_server(): void
    {
        $this->seedStore();
        $this->usShippingZone();
        $this->enableStripe();
        $product = $this->publishedProduct(['price_amount' => 2600, 'currency' => 'USD']);

        $hash = $this->currentCartHash($product, 2);

        $response = $this->withHeader('Idempotency-Key', $this->idempotencyKey())
            ->postJson('/api/v1/checkout/initialize', $this->checkoutPayload($product, $hash))
            ->assertCreated();

        // 2 x 2600 subtotal + 599 standard shipping.
        $response->assertJsonPath('data.order.totals.subtotal.amount', 5200)
            ->assertJsonPath('data.order.totals.shipping.amount', 599)
            ->assertJsonPath('data.order.totals.total.amount', 5799)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.paymentStatus', 'unpaid');
    }

    public function test_replaying_an_idempotency_key_returns_the_same_order(): void
    {
        $this->seedStore();
        $this->usShippingZone();
        $this->enableStripe();
        $product = $this->publishedProduct();

        $hash = $this->currentCartHash($product, 2);
        $payload = $this->checkoutPayload($product, $hash);
        $key = $this->idempotencyKey();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/checkout/initialize', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/checkout/initialize', $payload)
            ->assertOk();

        $this->assertSame(
            $first->json('data.order.orderNumber'),
            $second->json('data.order.orderNumber'),
        );
        $this->assertSame(1, Order::query()->count());
    }

    public function test_checkout_does_not_commit_inventory_before_payment(): void
    {
        $this->seedStore();
        $this->usShippingZone();
        $this->enableStripe();
        $product = $this->publishedProduct(['stock_quantity' => 10]);

        $hash = $this->currentCartHash($product, 2);

        $this->withHeader('Idempotency-Key', $this->idempotencyKey())
            ->postJson('/api/v1/checkout/initialize', $this->checkoutPayload($product, $hash))
            ->assertCreated();

        // Stock is committed on confirmed payment only, so an abandoned
        // checkout cannot drain availability.
        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertNull(Order::query()->firstOrFail()->inventory_committed_at);
    }

    public function test_checkout_is_refused_when_the_provider_is_disabled(): void
    {
        $this->seedStore();
        $this->usShippingZone();
        // Stripe deliberately NOT enabled.
        $product = $this->publishedProduct();

        $hash = $this->currentCartHash($product, 2);

        $this->withHeader('Idempotency-Key', $this->idempotencyKey())
            ->postJson('/api/v1/checkout/initialize', $this->checkoutPayload($product, $hash))
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIGURED');
    }

    /**
     * Asks the server for the hash it currently considers valid, which is the
     * only way a client can legitimately obtain one.
     */
    private function currentCartHash(Product $product, int $quantity): string
    {
        return (string) $this->postJson('/api/v1/cart/validate', [
            'items' => [['productId' => $product->id, 'variantId' => null, 'quantity' => $quantity]],
            'countryCode' => 'US',
        ])->json('data.cartHash');
    }
}
