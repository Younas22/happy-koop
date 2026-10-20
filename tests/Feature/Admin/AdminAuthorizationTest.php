<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Admin access control and payment-secret privacy.
 *
 * These assert the properties api-contract.md 2.2, 8 and 8.2 promise, which
 * are the ones a regression would quietly turn into a data leak rather than a
 * visible bug.
 */
class AdminAuthorizationTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    public function test_admin_endpoint_rejects_an_unauthenticated_request(): void
    {
        $this->seedStore();

        // No Accept header games: every /api/* request must produce the JSON
        // envelope, never a redirect to a nonexistent login route.
        $this->getJson('/api/v1/admin/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_admin_endpoint_rejects_a_customer_token(): void
    {
        $customer = $this->customerUser();

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_endpoint_allows_an_admin(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk();
    }

    public function test_payment_settings_never_return_a_stored_secret(): void
    {
        $admin = $this->adminUser();
        $this->enableStripe(); // writes sk_test_fixtureSecret4242

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $response = $this->getJson('/api/v1/admin/settings/payments')
            ->assertOk();

        // The decrypted value must not appear anywhere in the body, under any
        // key, at any nesting depth.
        $this->assertStringNotContainsString('sk_test_fixtureSecret4242', $response->getContent());
        $this->assertStringNotContainsString('whsec_fixtureWebhookSecret', $response->getContent());

        // Only status metadata is exposed.
        $response->assertJsonPath('data.stripe.secretKey.configured', true)
            ->assertJsonPath('data.stripe.secretKey.maskedSuffix', '4242');
    }

    public function test_public_runtime_config_never_exposes_a_payment_secret(): void
    {
        $this->seedStore();
        $this->enableStripe();

        $response = $this->getJson('/api/v1/public/runtime-config')->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('sk_test_fixtureSecret4242', $body);
        $this->assertStringNotContainsString('whsec_fixtureWebhookSecret', $body);

        // The secret-bearing keys must be absent entirely, not merely nulled.
        $response->assertJsonMissingPath('data.payments.stripe.secretKey')
            ->assertJsonMissingPath('data.payments.stripe.webhookSecret')
            ->assertJsonMissingPath('data.payments.paypal.clientSecret');

        // The publishable key is public, and only while the provider is on.
        $response->assertJsonPath('data.payments.stripe.publishableKey', 'pk_test_fixture');
    }

    public function test_publishable_key_is_withheld_while_the_provider_is_disabled(): void
    {
        $this->seedStore();
        $this->enableStripe();
        $this->settings()->set('payments.stripe.enabled', false);

        $this->getJson('/api/v1/public/runtime-config')
            ->assertOk()
            ->assertJsonPath('data.payments.stripe.enabled', false)
            ->assertJsonPath('data.payments.stripe.publishableKey', null);
    }

    public function test_an_omitted_secret_field_leaves_the_stored_secret_unchanged(): void
    {
        $admin = $this->adminUser();
        $this->enableStripe();

        // The admin form submits without re-entering the secret.
        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->putJson('/api/v1/admin/settings/payments', [
            'stripe' => [
                'enabled' => true,
                'mode' => 'test',
                'publishableKey' => 'pk_test_rotated',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.stripe.publishableKey', 'pk_test_rotated')
            ->assertJsonPath('data.stripe.secretKey.configured', true);

        // getSecret() is the only accessor that decrypts. The generic get()
        // deliberately returns null for an encrypted setting, so a secret
        // cannot leak through the common read path.
        $this->assertSame(
            'sk_test_fixtureSecret4242',
            $this->settings()->getSecret('payments.stripe.secret_key')
        );
        $this->assertNull($this->settings()->get('payments.stripe.secret_key'));
    }

    public function test_enabling_live_mode_without_explicit_confirmation_is_locked(): void
    {
        $admin = $this->adminUser();
        $this->enableStripe();

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->putJson('/api/v1/admin/settings/payments', [
            'stripe' => ['enabled' => true, 'mode' => 'live'],
        ])
            ->assertStatus(423)
            ->assertJsonPath('code', 'LIVE_MODE_LOCKED');

        // The stored mode must not have moved.
        $this->assertSame('test', $this->settings()->get('payments.stripe.mode'));
    }

    public function test_store_settings_response_carries_the_infrastructure_notice(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $response = $this->getJson('/api/v1/admin/settings/store')
            ->assertOk();

        // The admin UI renders this verbatim; it is what stops a store owner
        // believing a settings save repoints DNS or Vercel.
        $notice = $response->json('data.infrastructureNotice');
        $this->assertIsString($notice);
        $this->assertStringContainsString('does not change DNS', $notice);
    }

    public function test_store_settings_reject_a_non_http_api_base_url(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin, ['customer', 'admin']);

        $this->putJson('/api/v1/admin/settings/store', [
            'activeApiBaseUrl' => 'javascript:alert(1)',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }
}
