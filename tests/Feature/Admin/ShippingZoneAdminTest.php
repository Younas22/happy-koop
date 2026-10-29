<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Admin management of shipping zones and rates, which is the only thing that
 * decides whether checkout can deliver to a country.
 */
class ShippingZoneAdminTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
    }

    /**
     * @return array<string, mixed>
     */
    private function beneluxPayload(): array
    {
        return [
            'name' => 'Benelux',
            'countryCodes' => ['be', 'NL', 'LU'],
            'rates' => [
                [
                    'code' => 'standard',
                    'label' => 'Standard',
                    'priceAmount' => 495,
                    'freeAboveAmount' => 5000,
                    'estimateMinDays' => 1,
                    'estimateMaxDays' => 3,
                    'isDefault' => true,
                ],
            ],
        ];
    }

    public function test_a_created_zone_makes_its_countries_deliverable_at_checkout(): void
    {
        $this->settings()->set('locale.currency', 'EUR');
        $this->actAsAdmin();

        $this->getJson('/api/v1/shipping/options?country_code=BE')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/admin/shipping-zones', $this->beneluxPayload())
            ->assertCreated()
            ->assertJsonPath('data.countryCodes', ['BE', 'NL', 'LU'])
            ->assertJsonPath('data.rates.0.price.amount', 495)
            ->assertJsonPath('data.rates.0.price.currency', 'EUR');

        $this->getJson('/api/v1/shipping/options?country_code=BE')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rates_are_replaced_as_a_set(): void
    {
        $this->actAsAdmin();

        $id = $this->postJson('/api/v1/admin/shipping-zones', $this->beneluxPayload())->json('data.id');
        $standardId = $this->getJson('/api/v1/admin/shipping-zones')->json('data.0.rates.0.id');

        $this->patchJson("/api/v1/admin/shipping-zones/{$id}", [
            'rates' => [
                ['code' => 'express', 'label' => 'Express', 'priceAmount' => 1295],
                ['id' => $standardId, 'code' => 'standard', 'label' => 'Standard (bpost)', 'priceAmount' => 595],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.rates.0.code', 'express')
            ->assertJsonPath('data.rates.1.id', $standardId)
            ->assertJsonPath('data.rates.1.label', 'Standard (bpost)');

        $this->patchJson("/api/v1/admin/shipping-zones/{$id}", ['rates' => []])->assertOk();

        $this->assertSame(0, ShippingRate::query()->count());
    }

    public function test_a_country_cannot_belong_to_two_zones(): void
    {
        $this->actAsAdmin();
        $this->usShippingZone();

        $this->postJson('/api/v1/admin/shipping-zones', [
            'name' => 'North America',
            'countryCodes' => ['CA', 'US'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.countryCodes.0', 'Already covered by the zone "Test US": US. A country can belong to one zone only.');
    }

    public function test_invalid_rate_input_is_rejected_with_field_errors(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/v1/admin/shipping-zones', [
            'name' => 'Bad',
            'countryCodes' => ['XYZ'],
        ])->assertStatus(422)->assertJsonValidationErrors(['countryCodes.0']);

        $payload = $this->beneluxPayload();
        $payload['rates'][0]['estimateMinDays'] = 5;
        $payload['rates'][0]['estimateMaxDays'] = 2;

        $this->postJson('/api/v1/admin/shipping-zones', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rates.0.estimateMaxDays']);

        $this->assertSame(0, ShippingZone::query()->count());
    }

    public function test_deleting_a_zone_removes_its_rates(): void
    {
        $this->actAsAdmin();
        $zone = $this->usShippingZone();

        $this->deleteJson("/api/v1/admin/shipping-zones/{$zone->id}")->assertNoContent();

        $this->assertSame(0, ShippingZone::query()->count());
        $this->assertSame(0, ShippingRate::query()->count());
    }

    public function test_a_customer_cannot_manage_shipping_zones(): void
    {
        Sanctum::actingAs($this->customerUser(), ['customer']);

        $this->getJson('/api/v1/admin/shipping-zones')->assertStatus(403);
        $this->postJson('/api/v1/admin/shipping-zones', $this->beneluxPayload())->assertStatus(403);
    }
}
