<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Http\Requests\ApiRequest;

class CartValidateRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:'.config('happykoop.checkout.max_lines', 50)],
            'items.*.productId' => ['required', 'integer', 'min:1'],
            'items.*.variantId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.quantity' => [
                'required', 'integer', 'min:1',
                'max:'.config('happykoop.checkout.max_quantity_per_line', 99),
            ],
            'couponCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'countryCode' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
