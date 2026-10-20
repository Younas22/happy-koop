<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

/**
 * The single declaration of every settings key the application knows about:
 * its storage type, group, default, and whether it may ever be read by an
 * unauthenticated caller.
 *
 * Nothing outside this table can be written through the settings endpoints,
 * and `is_public` here is the allowlist that protects the public
 * runtime-config endpoint.
 */
final class SettingsRegistry
{
    /**
     * key => [type, group, public, default]
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: mixed}>
     */
    public const DEFINITIONS = [
        // ---------------------------------------------------------- store
        'store.name' => [Setting::TYPE_STRING, 'store', true, 'HappyKoop'],
        'store.tagline' => [Setting::TYPE_STRING, 'store', true, null],
        'store.logo_media_id' => [Setting::TYPE_INT, 'store', true, null],
        'store.logo_dark_media_id' => [Setting::TYPE_INT, 'store', true, null],
        'store.favicon_media_id' => [Setting::TYPE_INT, 'store', true, null],
        'store.contact_email' => [Setting::TYPE_STRING, 'store', true, null],
        'store.contact_phone' => [Setting::TYPE_STRING, 'store', true, null],
        'store.address_lines' => [Setting::TYPE_JSON, 'store', true, []],

        // ---------------------------------------------------------- theme
        'theme.primary_color' => [Setting::TYPE_STRING, 'theme', true, '#2563EB'],
        'theme.accent_color' => [Setting::TYPE_STRING, 'theme', true, '#2563EB'],
        'theme.radius' => [Setting::TYPE_STRING, 'theme', true, 'md'],

        // --------------------------------------------------------- locale
        'locale.currency' => [Setting::TYPE_STRING, 'locale', true, 'USD'],
        'locale.locale' => [Setting::TYPE_STRING, 'locale', true, 'en-US'],
        'locale.timezone' => [Setting::TYPE_STRING, 'locale', true, 'UTC'],

        // ----------------------------------------------------------- urls
        'urls.api_base_url' => [Setting::TYPE_STRING, 'urls', true, null],
        'urls.canonical_frontend_url' => [Setting::TYPE_STRING, 'urls', true, null],

        // ------------------------------------------------------------ seo
        'seo.default_title' => [Setting::TYPE_STRING, 'seo', true, 'HappyKoop'],
        'seo.title_template' => [Setting::TYPE_STRING, 'seo', true, '%s | HappyKoop'],
        'seo.default_description' => [Setting::TYPE_STRING, 'seo', true, null],
        'seo.default_og_image_media_id' => [Setting::TYPE_INT, 'seo', true, null],

        // ------------------------------------------------------- policies
        'policies.shipping_summary' => [Setting::TYPE_STRING, 'policies', true, null],
        'policies.returns_summary' => [Setting::TYPE_STRING, 'policies', true, null],

        // ------------------------------------------------------- features
        'features.reviews' => [Setting::TYPE_BOOL, 'features', true, true],
        'features.wishlist' => [Setting::TYPE_BOOL, 'features', true, false],
        'features.newsletter' => [Setting::TYPE_BOOL, 'features', true, false],
        'features.guest_checkout' => [Setting::TYPE_BOOL, 'features', true, true],
        'features.search' => [Setting::TYPE_BOOL, 'features', true, true],
        'features.public_stock_counts' => [Setting::TYPE_BOOL, 'features', true, false],

        // --------------------------------------------------------- social
        'social.instagram' => [Setting::TYPE_STRING, 'social', true, null],
        'social.facebook' => [Setting::TYPE_STRING, 'social', true, null],
        'social.tiktok' => [Setting::TYPE_STRING, 'social', true, null],
        'social.x' => [Setting::TYPE_STRING, 'social', true, null],

        // ----------------------------------------------------- navigation
        'navigation.primary' => [Setting::TYPE_JSON, 'navigation', true, []],
        'navigation.footer' => [Setting::TYPE_JSON, 'navigation', true, []],

        // --------------------------------------------------------- config
        'config.version' => [Setting::TYPE_STRING, 'config', true, null],

        // ----------------------------------------- payments: stripe
        // `enabled` and `mode` are public only through the curated payments
        // block of runtime-config, never as raw settings.
        'payments.stripe.enabled' => [Setting::TYPE_BOOL, 'payments', false, false],
        'payments.stripe.mode' => [Setting::TYPE_STRING, 'payments', false, 'test'],
        'payments.stripe.publishable_key' => [Setting::TYPE_STRING, 'payments', false, null],
        'payments.stripe.secret_key' => [Setting::TYPE_ENCRYPTED, 'payments', false, null],
        'payments.stripe.webhook_secret' => [Setting::TYPE_ENCRYPTED, 'payments', false, null],
        'payments.stripe.last_check' => [Setting::TYPE_JSON, 'payments', false, null],

        // ----------------------------------------- payments: paypal
        'payments.paypal.enabled' => [Setting::TYPE_BOOL, 'payments', false, false],
        'payments.paypal.mode' => [Setting::TYPE_STRING, 'payments', false, 'sandbox'],
        'payments.paypal.client_id' => [Setting::TYPE_STRING, 'payments', false, null],
        'payments.paypal.client_secret' => [Setting::TYPE_ENCRYPTED, 'payments', false, null],
        'payments.paypal.webhook_id' => [Setting::TYPE_ENCRYPTED, 'payments', false, null],
        'payments.paypal.last_check' => [Setting::TYPE_JSON, 'payments', false, null],
    ];

    /**
     * Keys whose value is a secret. These are stored encrypted and may never
     * be returned by any endpoint, in any field, in any error.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = [
        'payments.stripe.secret_key',
        'payments.stripe.webhook_secret',
        'payments.paypal.client_secret',
        'payments.paypal.webhook_id',
    ];

    public static function has(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    public static function type(string $key): string
    {
        return self::DEFINITIONS[$key][0] ?? Setting::TYPE_STRING;
    }

    public static function group(string $key): string
    {
        return self::DEFINITIONS[$key][1] ?? 'general';
    }

    public static function isPublic(string $key): bool
    {
        return (bool) (self::DEFINITIONS[$key][2] ?? false);
    }

    public static function default(string $key): mixed
    {
        return self::DEFINITIONS[$key][3] ?? null;
    }

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return list<string>
     */
    public static function publicKeys(): array
    {
        return array_values(array_filter(self::keys(), static fn (string $key): bool => self::isPublic($key)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $out = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $out[$key] = $definition[3];
        }

        return $out;
    }
}
