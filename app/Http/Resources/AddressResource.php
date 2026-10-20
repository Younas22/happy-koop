<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Address;

final class AddressResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Address $address): array
    {
        return [
            'id' => (int) $address->id,
            'firstName' => $address->first_name,
            'lastName' => $address->last_name,
            'company' => $address->company,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postalCode' => $address->postal_code,
            'countryCode' => $address->country_code,
            'phone' => $address->phone,
            'isDefaultShipping' => (bool) $address->is_default_shipping,
            'isDefaultBilling' => (bool) $address->is_default_billing,
            'createdAt' => $address->created_at?->toIso8601String(),
        ];
    }
}
