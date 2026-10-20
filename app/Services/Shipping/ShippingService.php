<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Shipping rates, read strictly from administrator-configured zones and rates
 * (api-contract.md 5).
 *
 * Returns an empty option list for a destination the store has not configured.
 * It never falls back to "free", and never invents a delivery estimate.
 */
class ShippingService
{
    /**
     * The zone that covers a destination: an explicit country match wins over
     * a catch-all ('*') zone.
     */
    public function zoneFor(string $countryCode): ?ShippingZone
    {
        $zones = ShippingZone::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $explicit = $zones->first(function (ShippingZone $zone) use ($countryCode): bool {
            $codes = array_map('strtoupper', (array) ($zone->country_codes ?? []));

            return in_array(strtoupper($countryCode), $codes, true);
        });

        if ($explicit !== null) {
            return $explicit;
        }

        return $zones->first(static function (ShippingZone $zone): bool {
            return in_array('*', array_map('strtoupper', (array) ($zone->country_codes ?? [])), true);
        });
    }

    /**
     * Available options for a destination and subtotal.
     *
     * @return Collection<int, array{rate: ShippingRate, charge: Money}>
     */
    public function optionsFor(string $countryCode, Money $subtotal): Collection
    {
        $zone = $this->zoneFor($countryCode);

        if ($zone === null) {
            return collect();
        }

        return $zone->rates()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(static fn (ShippingRate $rate): bool => $rate->currency === $subtotal->currency)
            ->filter(static fn (ShippingRate $rate): bool => $rate->availableFor($subtotal))
            ->map(static fn (ShippingRate $rate): array => [
                'rate' => $rate,
                'charge' => $rate->chargeFor($subtotal),
            ])
            ->values();
    }

    /**
     * Resolves a client-selected method id for a destination. Returns null
     * when the method is not offered there — the caller then rejects the
     * checkout rather than guessing a price.
     */
    public function resolveRate(string $code, string $countryCode, Money $subtotal): ?ShippingRate
    {
        foreach ($this->optionsFor($countryCode, $subtotal) as $option) {
            if ($option['rate']->code === $code) {
                return $option['rate'];
            }
        }

        return null;
    }

    /**
     * A free-shipping threshold is advertised on product pages only when every
     * configured rate that has one agrees on the same value. Otherwise the
     * threshold depends on the destination and is reported as unknown.
     */
    public function globalFreeShippingThreshold(string $currency): ?Money
    {
        $thresholds = ShippingRate::query()
            ->active()
            ->where('currency', $currency)
            ->whereNotNull('free_above_amount')
            ->distinct()
            ->pluck('free_above_amount')
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values();

        if ($thresholds->count() !== 1) {
            return null;
        }

        return Money::of((int) $thresholds->first(), $currency);
    }
}
