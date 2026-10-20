<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Product;

/**
 * Replaces the whole option/variant set for a product atomically.
 */
class ProductVariantsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'options' => ['present', 'array', 'max:3'],
            'options.*.name' => ['required', 'string', 'max:60'],
            'options.*.slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:40'],
            'options.*.values.*.label' => ['required', 'string', 'max:80'],
            'options.*.values.*.value' => ['required', 'string', 'max:80'],
            'options.*.values.*.swatchHex' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'variants' => ['present', 'array', 'max:200'],
            'variants.*.sku' => ['sometimes', 'nullable', 'string', 'max:64'],
            'variants.*.priceAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999999999'],
            'variants.*.compareAtAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999999999'],
            'variants.*.stockQuantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'variants.*.inventoryPolicy' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', [
                Product::POLICY_TRACK, Product::POLICY_CONTINUE, Product::POLICY_DENY,
            ])],
            'variants.*.weightGrams' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.mediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'variants.*.optionValues' => ['present', 'array', 'max:3'],
            'variants.*.optionValues.*' => ['string', 'max:80'],
            'variants.*.isDefault' => ['sometimes', 'boolean'],
            'variants.*.isActive' => ['sometimes', 'boolean'],
        ];
    }
}
