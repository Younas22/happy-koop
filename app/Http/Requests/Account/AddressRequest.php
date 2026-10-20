<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiRequest;

class AddressRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'company' => ['sometimes', 'nullable', 'string', 'max:160'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postalCode' => ['required', 'string', 'max:32'],
            'countryCode' => ['required', 'string', 'size:2', 'alpha'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'isDefaultShipping' => ['sometimes', 'boolean'],
            'isDefaultBilling' => ['sometimes', 'boolean'],
        ];
    }
}
