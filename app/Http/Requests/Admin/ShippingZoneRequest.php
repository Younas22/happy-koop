<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

/**
 * A shipping zone and, optionally, its full rate list.
 *
 * `rates`, when present, REPLACES the zone's rates: a rate with an `id` is
 * updated, one without is created, and any rate left out is deleted. Amounts
 * are integer minor units in the store currency (api-contract.md 1.5).
 */
class ShippingZoneRequest extends ApiRequest
{
    private const MAX_AMOUNT = 100_000_000;

    protected function prepareForValidation(): void
    {
        $codes = $this->input('countryCodes');

        if (is_array($codes)) {
            $this->merge([
                'countryCodes' => array_values(array_map(
                    static fn ($code) => is_string($code) ? strtoupper(trim($code)) : $code,
                    $codes
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:160'],
            'countryCodes' => [$required, 'array', 'min:1', 'max:300'],
            'countryCodes.*' => ['required', 'string', 'distinct', 'regex:/^([A-Z]{2}|\*)$/'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0', 'max:10000'],

            'rates' => ['sometimes', 'array', 'max:20'],
            'rates.*.id' => ['sometimes', 'nullable', 'integer'],
            'rates.*.code' => ['required', 'string', 'max:64', 'distinct', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'rates.*.label' => ['required', 'string', 'min:1', 'max:160'],
            'rates.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rates.*.priceAmount' => ['required', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'rates.*.minSubtotalAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'rates.*.freeAboveAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'rates.*.estimateMinDays' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'rates.*.estimateMaxDays' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'rates.*.isDefault' => ['sometimes', 'boolean'],
            'rates.*.isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'countryCodes.*.regex' => 'Each country must be a two-letter ISO code, or * for every other country.',
            'countryCodes.*.distinct' => 'A country is listed twice in this zone.',
            'rates.*.code.regex' => 'The rate code may only contain lowercase letters, numbers, - and _.',
            'rates.*.code.distinct' => 'Two rates in this zone use the same code.',
        ];
    }
}
