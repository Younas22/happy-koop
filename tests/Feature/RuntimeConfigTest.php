<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

class RuntimeConfigTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    public function test_runtime_config_returns_the_contract_shape(): void
    {
        $this->seedStore();

        $response = $this->getJson('/api/v1/public/runtime-config');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'configVersion',
                    'store' => ['name', 'tagline', 'logoUrl', 'logoDarkUrl', 'faviconUrl', 'email', 'phone', 'addressLines'],
                    'theme' => ['primaryColor', 'accentColor', 'radius'],
                    'locale' => ['currency', 'currencySymbol', 'minorUnitExponent', 'locale', 'timezone'],
                    'urls' => ['apiBaseUrl', 'canonicalFrontendUrl'],
                    'seo' => ['defaultTitle', 'titleTemplate', 'defaultDescription', 'defaultOgImageUrl'],
                    'policies' => ['shippingSummary', 'returnsSummary'],
                    'features' => ['reviews', 'wishlist', 'newsletter', 'guestCheckout', 'search', 'publicStockCounts'],
                    'payments' => ['stripe' => ['enabled', 'mode', 'publishableKey'], 'paypal' => ['enabled', 'mode', 'clientId']],
                    'social' => ['instagram', 'facebook', 'tiktok', 'x'],
                    'navigation' => ['primary', 'footer'],
                ],
            ]);

        // Symfony normalizes directive order; order is not significant in HTTP.
        $cacheControl = (string) $response->headers->get('Cache-Control');

        foreach (['public', 'max-age=60', 'stale-while-revalidate=300'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
    }

    public function test_runtime_config_never_contains_a_secret(): void
    {
        $this->seedStore();
        $this->enableStripe();

        $settings = $this->settings();
        $settings->set('payments.paypal.enabled', true);
        $settings->set('payments.paypal.client_id', 'paypal-public-client-id');
        $settings->set('payments.paypal.client_secret', 'paypal-super-secret-value');
        $settings->set('payments.paypal.webhook_id', 'WH-SECRET-ID-123');

        $raw = $this->getJson('/api/v1/public/runtime-config')->assertOk()->getContent();

        // The seeded secret VALUES must not appear anywhere in the payload.
        $this->assertStringNotContainsString('sk_test_fixtureSecret4242', $raw);
        $this->assertStringNotContainsString('whsec_fixtureWebhookSecret', $raw);
        $this->assertStringNotContainsString('paypal-super-secret-value', $raw);
        $this->assertStringNotContainsString('WH-SECRET-ID-123', $raw);

        // Nor may the secret KEY NAMES be present under any key.
        foreach (['secretKey', 'secret_key', 'webhookSecret', 'webhook_secret', 'clientSecret', 'client_secret', 'webhookId'] as $key) {
            $this->assertStringNotContainsString($key, $raw, "runtime-config leaked key [{$key}]");
        }

        // The publishable identifiers ARE expected, since both providers are on.
        $this->assertStringContainsString('pk_test_fixture', $raw);
        $this->assertStringContainsString('paypal-public-client-id', $raw);
    }

    public function test_publishable_identifiers_are_hidden_while_a_provider_is_disabled(): void
    {
        $this->seedStore();

        $settings = $this->settings();
        $settings->set('payments.stripe.enabled', false);
        $settings->set('payments.stripe.publishable_key', 'pk_test_hidden');

        $this->getJson('/api/v1/public/runtime-config')
            ->assertOk()
            ->assertJsonPath('data.payments.stripe.enabled', false)
            ->assertJsonPath('data.payments.stripe.publishableKey', null);
    }
}
