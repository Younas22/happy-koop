<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * One example zone with two rates, so checkout is testable locally.
 *
 * Delivery estimates are the ones written here explicitly; nothing is
 * inferred or invented at request time.
 */
class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $zone = ShippingZone::query()->firstOrCreate(
            ['name' => 'Sample zone — United States'],
            [
                'country_codes' => ['US'],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $rates = [
            [
                'code' => 'standard',
                'label' => 'Standard',
                'description' => '3-7 business days',
                'price_amount' => 599,
                'free_above_amount' => 7500,
                'estimate_min_days' => 3,
                'estimate_max_days' => 7,
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'express',
                'label' => 'Express',
                'description' => '1-3 business days',
                'price_amount' => 1499,
                'free_above_amount' => null,
                'estimate_min_days' => 1,
                'estimate_max_days' => 3,
                'is_default' => false,
                'sort_order' => 1,
            ],
        ];

        foreach ($rates as $rate) {
            $zone->rates()->firstOrCreate(
                ['code' => $rate['code']],
                $rate + ['currency' => 'USD', 'is_active' => true]
            );
        }

        $this->command?->info('Sample shipping zone and rates ensured.');
    }
}
