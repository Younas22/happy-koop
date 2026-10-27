<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Support\CurrencyRegistry;
use Illuminate\Support\Facades\DB;

/**
 * The store sells in exactly one currency: the `locale.currency` setting.
 *
 * Products, shipping rates and fixed-amount coupons each carry a currency
 * column, and the cart and shipping lookups only accept rows in the store
 * currency. If those columns drift from the setting, prices show the wrong
 * symbol, products become unbuyable (`currency_mismatch`) and no shipping rate
 * matches. So whenever the store currency changes, those rows follow it.
 *
 * Amounts are NOT converted: there is no exchange-rate source, and an admin
 * switching currency is re-labelling the prices they already entered. Orders
 * are never touched - each keeps the currency it was placed in.
 */
class StoreCurrency
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function code(): string
    {
        return CurrencyRegistry::normalize($this->settings->getString('locale.currency', 'USD'));
    }

    /**
     * Moves every catalog-side row to `$currency`. Returns how many rows
     * changed, per table.
     *
     * @return array{products: int, shipping_rates: int, coupons: int}
     */
    public function syncCatalog(?string $currency = null): array
    {
        $currency = CurrencyRegistry::normalize($currency ?? $this->code());

        return DB::transaction(static fn (): array => [
            'products' => DB::table('products')
                ->where('currency', '!=', $currency)
                ->update(['currency' => $currency, 'updated_at' => now()]),
            'shipping_rates' => DB::table('shipping_rates')
                ->where('currency', '!=', $currency)
                ->update(['currency' => $currency, 'updated_at' => now()]),
            // Percent coupons have no currency; only fixed amounts are moved.
            'coupons' => DB::table('coupons')
                ->whereNotNull('currency')
                ->where('currency', '!=', $currency)
                ->update(['currency' => $currency, 'updated_at' => now()]),
        ]);
    }
}
