<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Services\Settings\SettingsRepository;
use App\Services\Shipping\ShippingService;
use App\Support\CurrencyRegistry;

/**
 * Store-level context shared by the public catalog endpoints: the active
 * currency, whether public stock counts are enabled, and the policy text a
 * product falls back to when it has none of its own.
 */
class CatalogContext
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ShippingService $shipping,
    ) {}

    public function currency(): string
    {
        return CurrencyRegistry::normalize($this->settings->getString('locale.currency', 'USD'));
    }

    public function publicStockCounts(): bool
    {
        return $this->settings->getBool('features.public_stock_counts');
    }

    public function reviewsEnabled(): bool
    {
        return $this->settings->getBool('features.reviews', true);
    }

    public function availability(): AvailabilityResolver
    {
        return new AvailabilityResolver($this->publicStockCounts());
    }

    /**
     * Fallbacks for the product shipping/returns blocks. Each value is either
     * administrator-configured or null — nothing here is invented.
     *
     * @return array<string, mixed>
     */
    public function storeDefaults(): array
    {
        return [
            'shippingSummary' => $this->settings->getString('policies.shipping_summary'),
            'returnsSummary' => $this->settings->getString('policies.returns_summary'),
            'estimateText' => null,
            'freeShippingThreshold' => $this->shipping->globalFreeShippingThreshold($this->currency()),
        ];
    }
}
