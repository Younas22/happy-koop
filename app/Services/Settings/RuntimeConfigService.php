<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Media;
use App\Support\CurrencyRegistry;
use App\Support\MoneyFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Builds the public runtime-config payload (api-contract.md 3).
 *
 * Every value is read through `value()`, which refuses any key that is not in
 * ALLOWED_KEYS. That makes it structurally impossible for a newly added
 * setting — a secret in particular — to appear here by accident.
 */
class RuntimeConfigService
{
    public const CACHE_KEY = 'happykoop.runtime_config.v1';

    public const CACHE_SECONDS = 60;

    /**
     * The ONLY settings keys this endpoint may read.
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        'config.version',
        'store.name',
        'store.tagline',
        'store.logo_media_id',
        'store.logo_dark_media_id',
        'store.favicon_media_id',
        'store.contact_email',
        'store.contact_phone',
        'store.address_lines',
        'theme.primary_color',
        'theme.accent_color',
        'theme.radius',
        'locale.currency',
        'locale.locale',
        'locale.timezone',
        'urls.api_base_url',
        'urls.canonical_frontend_url',
        'seo.default_title',
        'seo.title_template',
        'seo.default_description',
        'seo.default_og_image_media_id',
        'policies.shipping_summary',
        'policies.returns_summary',
        'features.reviews',
        'features.wishlist',
        'features.newsletter',
        'features.guest_checkout',
        'features.search',
        'features.public_stock_counts',
        'social.instagram',
        'social.facebook',
        'social.tiktok',
        'social.x',
        'navigation.primary',
        'navigation.footer',
        // Provider enablement + mode only. The publishable key / client id are
        // read through dedicated, enablement-gated accessors below.
        'payments.stripe.enabled',
        'payments.stripe.mode',
        'payments.paypal.enabled',
        'payments.paypal.mode',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->build());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Bumps `configVersion` and drops the cached payload. Called whenever a
     * store setting is saved.
     */
    public function bumpVersion(?int $userId = null): string
    {
        $version = Carbon::now()->toIso8601String();

        $this->settings->set('config.version', $version, $userId);
        $this->flush();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $currency = CurrencyRegistry::normalize($this->value('locale.currency'));
        $locale = $this->value('locale.locale');
        $locale = MoneyFormatter::supportsLocale(is_string($locale) ? $locale : null)
            ? $locale
            : MoneyFormatter::DEFAULT_LOCALE;

        return [
            'configVersion' => $this->value('config.version')
                ?? Carbon::createFromTimestamp(0)->toIso8601String(),
            'store' => [
                'name' => $this->value('store.name'),
                'tagline' => $this->value('store.tagline'),
                'logoUrl' => $this->mediaUrl($this->value('store.logo_media_id')),
                'logoDarkUrl' => $this->mediaUrl($this->value('store.logo_dark_media_id')),
                'faviconUrl' => $this->mediaUrl($this->value('store.favicon_media_id')),
                'email' => $this->value('store.contact_email'),
                'phone' => $this->value('store.contact_phone'),
                'addressLines' => array_values((array) ($this->value('store.address_lines') ?? [])),
            ],
            'theme' => [
                'primaryColor' => $this->value('theme.primary_color'),
                'accentColor' => $this->value('theme.accent_color'),
                'radius' => $this->value('theme.radius'),
            ],
            'locale' => [
                'currency' => $currency,
                'currencySymbol' => CurrencyRegistry::symbol($currency),
                'minorUnitExponent' => CurrencyRegistry::minorUnitExponent($currency),
                'locale' => $locale,
                'timezone' => $this->value('locale.timezone') ?? 'UTC',
            ],
            'urls' => [
                'apiBaseUrl' => $this->value('urls.api_base_url')
                    ?? rtrim((string) config('app.url'), '/').'/api/v1',
                'canonicalFrontendUrl' => $this->value('urls.canonical_frontend_url')
                    ?? rtrim((string) config('happykoop.frontend_url'), '/'),
            ],
            'seo' => [
                'defaultTitle' => $this->value('seo.default_title'),
                'titleTemplate' => $this->value('seo.title_template'),
                'defaultDescription' => $this->value('seo.default_description'),
                'defaultOgImageUrl' => $this->mediaUrl($this->value('seo.default_og_image_media_id')),
            ],
            'policies' => [
                'shippingSummary' => $this->value('policies.shipping_summary'),
                'returnsSummary' => $this->value('policies.returns_summary'),
            ],
            'features' => [
                'reviews' => (bool) $this->value('features.reviews'),
                'wishlist' => (bool) $this->value('features.wishlist'),
                'newsletter' => (bool) $this->value('features.newsletter'),
                'guestCheckout' => (bool) $this->value('features.guest_checkout'),
                'search' => (bool) $this->value('features.search'),
                'publicStockCounts' => (bool) $this->value('features.public_stock_counts'),
            ],
            'payments' => $this->payments(),
            'social' => [
                'instagram' => $this->value('social.instagram'),
                'facebook' => $this->value('social.facebook'),
                'tiktok' => $this->value('social.tiktok'),
                'x' => $this->value('social.x'),
            ],
            'navigation' => [
                'primary' => array_values((array) ($this->value('navigation.primary') ?? [])),
                'footer' => array_values((array) ($this->value('navigation.footer') ?? [])),
            ],
        ];
    }

    /**
     * Publishable identifiers are exposed ONLY when the provider is enabled.
     * Secret keys, webhook secrets and the PayPal webhook id are not readable
     * from here at all — they are not in ALLOWED_KEYS.
     *
     * @return array<string, mixed>
     */
    private function payments(): array
    {
        $stripeEnabled = (bool) $this->value('payments.stripe.enabled');
        $paypalEnabled = (bool) $this->value('payments.paypal.enabled');

        return [
            'stripe' => [
                'enabled' => $stripeEnabled,
                'mode' => $this->value('payments.stripe.mode') ?? 'test',
                'publishableKey' => $stripeEnabled
                    ? $this->settings->getString('payments.stripe.publishable_key')
                    : null,
            ],
            'paypal' => [
                'enabled' => $paypalEnabled,
                'mode' => $this->value('payments.paypal.mode') ?? 'sandbox',
                'clientId' => $paypalEnabled
                    ? $this->settings->getString('payments.paypal.client_id')
                    : null,
            ],
        ];
    }

    private function value(string $key): mixed
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new LogicException(
                "Setting [{$key}] is not allowlisted for the public runtime config."
            );
        }

        if (SettingsRegistry::isSecret($key)) {
            throw new LogicException("Refusing to expose secret setting [{$key}].");
        }

        return $this->settings->get($key);
    }

    private function mediaUrl(mixed $mediaId): ?string
    {
        if (! is_int($mediaId) && ! (is_string($mediaId) && ctype_digit($mediaId))) {
            return null;
        }

        $media = Media::query()->find((int) $mediaId);

        return $media?->url();
    }
}
