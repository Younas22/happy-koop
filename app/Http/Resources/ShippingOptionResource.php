<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShippingRate;
use App\Support\Money;

final class ShippingOptionResource
{
    /**
     * Delivery estimates are null unless an administrator configured them —
     * dates are never invented (api-contract.md 5).
     *
     * @return array<string, mixed>
     */
    public static function make(ShippingRate $rate, Money $charge): array
    {
        return [
            'id' => $rate->code,
            'label' => $rate->label,
            'description' => $rate->description,
            'price' => MoneyResource::make($charge),
            'estimateMinDays' => $rate->estimate_min_days === null ? null : (int) $rate->estimate_min_days,
            'estimateMaxDays' => $rate->estimate_max_days === null ? null : (int) $rate->estimate_max_days,
            'isDefault' => (bool) $rate->is_default,
        ];
    }
}
