<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;

trait BuildsStoreFixtures
{
    protected function seedStore(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    protected function settings(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    protected function adminUser(): User
    {
        $this->seedStore();

        $user = User::factory()->create(['email' => 'admin@happykoop.test']);

        $user->roles()->sync(
            Role::query()->whereIn('slug', [Role::ADMIN, Role::CUSTOMER])->pluck('id')->all()
        );

        return $user->refresh();
    }

    protected function customerUser(?string $email = null): User
    {
        $this->seedStore();

        $user = User::factory()->create($email === null ? [] : ['email' => $email]);

        $user->roles()->sync(
            Role::query()->where('slug', Role::CUSTOMER)->pluck('id')->all()
        );

        return $user->refresh();
    }

    protected function enableStripe(): void
    {
        $settings = $this->settings();
        $settings->set('payments.stripe.enabled', true);
        $settings->set('payments.stripe.mode', 'test');
        $settings->set('payments.stripe.publishable_key', 'pk_test_fixture');
        $settings->set('payments.stripe.secret_key', 'sk_test_fixtureSecret4242');
        $settings->set('payments.stripe.webhook_secret', 'whsec_fixtureWebhookSecret');
    }

    protected function usShippingZone(): ShippingZone
    {
        $zone = ShippingZone::query()->create([
            'name' => 'Test US',
            'country_codes' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $zone->rates()->create([
            'code' => 'standard',
            'label' => 'Standard',
            'description' => '3-7 business days',
            'price_amount' => 599,
            'currency' => 'USD',
            'estimate_min_days' => 3,
            'estimate_max_days' => 7,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return $zone;
    }

    protected function publishedProduct(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutPayload(Product $product, string $cartHash, int $quantity = 2): array
    {
        return [
            'items' => [
                ['productId' => $product->id, 'variantId' => null, 'quantity' => $quantity],
            ],
            'cartHash' => $cartHash,
            'email' => 'buyer@example.test',
            'shippingAddress' => [
                'firstName' => 'Ada',
                'lastName' => 'Lovelace',
                'line1' => '1 Example St',
                'city' => 'Portland',
                'region' => 'OR',
                'postalCode' => '97201',
                'countryCode' => 'US',
            ],
            'billingAddress' => null,
            'shippingMethodId' => 'standard',
            'paymentProvider' => 'stripe',
        ];
    }
}
