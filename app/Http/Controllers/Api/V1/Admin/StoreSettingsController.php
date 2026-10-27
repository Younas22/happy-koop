<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Settings\RuntimeConfigService;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\StoreCurrency;
use App\Support\Html;
use App\Support\PublicUrl;
use Illuminate\Http\JsonResponse;

class StoreSettingsController extends ApiController
{
    /**
     * The advisory the admin UI must display verbatim (api-contract.md 8.1).
     */
    public const INFRASTRUCTURE_NOTICE = 'These are application settings. Saving them does not change DNS, TLS certificates, hosting, the Vercel project domain or its environment variables, CORS allowlists, cookie domains, or payment-provider webhook URLs. Each must be updated in its own system.';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly RuntimeConfigService $runtimeConfig,
        private readonly AuditLogger $audit,
        private readonly StoreCurrency $storeCurrency,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return $this->data($this->payload());
    }

    public function update(StoreSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', new Setting);

        /** @var User $actor */
        $actor = $request->user();

        $input = $request->validated();
        $writes = [];

        $map = [
            'storeName' => ['store.name', 'text'],
            'tagline' => ['store.tagline', 'text'],
            'logoMediaId' => ['store.logo_media_id', 'raw'],
            'logoDarkMediaId' => ['store.logo_dark_media_id', 'raw'],
            'faviconMediaId' => ['store.favicon_media_id', 'raw'],
            'primaryColor' => ['theme.primary_color', 'raw'],
            'accentColor' => ['theme.accent_color', 'raw'],
            'radius' => ['theme.radius', 'raw'],
            'contactEmail' => ['store.contact_email', 'text'],
            'contactPhone' => ['store.contact_phone', 'text'],
            'addressLines' => ['store.address_lines', 'lines'],
            'currency' => ['locale.currency', 'raw'],
            'locale' => ['locale.locale', 'raw'],
            'timezone' => ['locale.timezone', 'raw'],
            'canonicalFrontendUrl' => ['urls.canonical_frontend_url', 'url'],
            'activeApiBaseUrl' => ['urls.api_base_url', 'url'],
            'seoDefaultTitle' => ['seo.default_title', 'text'],
            'seoTitleTemplate' => ['seo.title_template', 'text'],
            'seoDefaultDescription' => ['seo.default_description', 'text'],
            'seoDefaultOgImageMediaId' => ['seo.default_og_image_media_id', 'raw'],
            'shippingSummary' => ['policies.shipping_summary', 'text'],
            'returnsSummary' => ['policies.returns_summary', 'text'],
        ];

        foreach ($map as $inputKey => [$settingKey, $mode]) {
            if (! array_key_exists($inputKey, $input)) {
                continue;
            }

            $value = $input[$inputKey];

            $value = match ($mode) {
                'text' => Html::text(is_string($value) ? $value : null, 2000),
                'url' => is_string($value) && $value !== '' ? PublicUrl::normalize($value) : null,
                'lines' => array_values(array_filter(array_map(
                    static fn ($line): string => trim(strip_tags((string) $line)),
                    (array) ($value ?? [])
                ), static fn (string $line): bool => $line !== '')),
                default => $value,
            };

            $this->settings->set($settingKey, $value, $actor->id);
            $writes[] = $settingKey;
        }

        foreach ((array) ($input['social'] ?? []) as $network => $url) {
            $key = 'social.'.$network;

            if (! SettingsRegistry::has($key)) {
                continue;
            }

            $this->settings->set(
                $key,
                is_string($url) && $url !== '' ? PublicUrl::normalize($url) : null,
                $actor->id
            );
            $writes[] = $key;
        }

        $featureMap = [
            'reviews' => 'features.reviews',
            'wishlist' => 'features.wishlist',
            'newsletter' => 'features.newsletter',
            'guestCheckout' => 'features.guest_checkout',
            'search' => 'features.search',
            'publicStockCounts' => 'features.public_stock_counts',
        ];

        foreach ((array) ($input['features'] ?? []) as $feature => $enabled) {
            if (! isset($featureMap[$feature])) {
                continue;
            }

            $this->settings->set($featureMap[$feature], (bool) $enabled, $actor->id);
            $writes[] = $featureMap[$feature];
        }

        if (isset($input['navigation']) && is_array($input['navigation'])) {
            foreach (['primary', 'footer'] as $part) {
                if (! array_key_exists($part, $input['navigation'])) {
                    continue;
                }

                $this->settings->set(
                    'navigation.'.$part,
                    array_values((array) $input['navigation'][$part]),
                    $actor->id
                );
                $writes[] = 'navigation.'.$part;
            }
        }

        // Products, shipping rates and fixed coupons follow the store currency.
        if (in_array('locale.currency', $writes, true)) {
            $this->storeCurrency->syncCatalog();
        }

        // Saving bumps configVersion and invalidates the runtime-config cache.
        $this->runtimeConfig->bumpVersion($actor->id);

        if ($writes !== []) {
            $this->audit->log('settings.store.updated', $actor->id, null, $writes, $request);
        }

        return $this->data($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'storeName' => $this->settings->getString('store.name'),
            'tagline' => $this->settings->getString('store.tagline'),
            'logoMediaId' => $this->settings->getInt('store.logo_media_id'),
            'logoDarkMediaId' => $this->settings->getInt('store.logo_dark_media_id'),
            'faviconMediaId' => $this->settings->getInt('store.favicon_media_id'),
            'primaryColor' => $this->settings->getString('theme.primary_color'),
            'accentColor' => $this->settings->getString('theme.accent_color'),
            'radius' => $this->settings->getString('theme.radius'),
            'contactEmail' => $this->settings->getString('store.contact_email'),
            'contactPhone' => $this->settings->getString('store.contact_phone'),
            'addressLines' => array_values($this->settings->getArray('store.address_lines')),
            'currency' => $this->settings->getString('locale.currency'),
            'locale' => $this->settings->getString('locale.locale'),
            'timezone' => $this->settings->getString('locale.timezone'),
            'canonicalFrontendUrl' => $this->settings->getString('urls.canonical_frontend_url'),
            'activeApiBaseUrl' => $this->settings->getString('urls.api_base_url'),
            'seoDefaultTitle' => $this->settings->getString('seo.default_title'),
            'seoTitleTemplate' => $this->settings->getString('seo.title_template'),
            'seoDefaultDescription' => $this->settings->getString('seo.default_description'),
            'seoDefaultOgImageMediaId' => $this->settings->getInt('seo.default_og_image_media_id'),
            'shippingSummary' => $this->settings->getString('policies.shipping_summary'),
            'returnsSummary' => $this->settings->getString('policies.returns_summary'),
            'social' => [
                'instagram' => $this->settings->getString('social.instagram'),
                'facebook' => $this->settings->getString('social.facebook'),
                'tiktok' => $this->settings->getString('social.tiktok'),
                'x' => $this->settings->getString('social.x'),
            ],
            'features' => [
                'reviews' => $this->settings->getBool('features.reviews', true),
                'wishlist' => $this->settings->getBool('features.wishlist'),
                'newsletter' => $this->settings->getBool('features.newsletter'),
                'guestCheckout' => $this->settings->getBool('features.guest_checkout', true),
                'search' => $this->settings->getBool('features.search', true),
                'publicStockCounts' => $this->settings->getBool('features.public_stock_counts'),
            ],
            'navigation' => [
                'primary' => array_values($this->settings->getArray('navigation.primary')),
                'footer' => array_values($this->settings->getArray('navigation.footer')),
            ],
            'configVersion' => $this->settings->getString('config.version'),
            'infrastructureNotice' => self::INFRASTRUCTURE_NOTICE,
        ];
    }
}
