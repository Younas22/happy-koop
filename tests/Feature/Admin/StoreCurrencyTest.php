<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * The store sells in one currency. Products, shipping rates and fixed coupons
 * must follow the `locale.currency` setting, or prices show the wrong symbol
 * and the cart rejects every product as a currency mismatch.
 */
class StoreCurrencyTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    public function test_changing_the_store_currency_moves_the_catalog_with_it(): void
    {
        $admin = $this->adminUser();
        $product = $this->publishedProduct(['price_amount' => 1990, 'currency' => 'USD']);
        $this->usShippingZone();

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->putJson('/api/v1/admin/settings/store', ['currency' => 'EUR'])->assertOk();

        $this->assertSame('EUR', $product->refresh()->currency);
        // The amount is re-labelled, not converted.
        $this->assertSame(1990, (int) $product->price_amount);
        $this->assertSame(['EUR'], DB::table('shipping_rates')->distinct()->pluck('currency')->all());

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.price.currency', 'EUR')
            ->assertJsonPath('data.price.formatted', fn (string $formatted): bool => str_contains($formatted, '€'));
    }

    public function test_a_new_product_always_takes_the_store_currency(): void
    {
        $admin = $this->adminUser();
        $this->settings()->set('locale.currency', 'EUR');

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Bamboo tray',
            'priceAmount' => 1500,
            'currency' => 'USD',
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'EUR');
    }
}
