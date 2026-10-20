<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Product;
use App\Support\CurrencyRegistry;
use Illuminate\Validation\Rule;

class ProductRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:255'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($id),
            ],
            'sku' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($id)],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'shortDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [
                Product::STATUS_DRAFT, Product::STATUS_PUBLISHED, Product::STATUS_ARCHIVED,
            ])],

            'priceAmount' => [$required, 'integer', 'min:0', 'max:99999999999'],
            'compareAtAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999999999'],
            'costAmount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999999999'],
            'currency' => ['sometimes', 'string', 'in:'.implode(',', CurrencyRegistry::codes())],

            'inventoryPolicy' => ['sometimes', 'string', 'in:'.implode(',', [
                Product::POLICY_TRACK, Product::POLICY_CONTINUE, Product::POLICY_DENY,
            ])],
            'stockQuantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'lowStockThreshold' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],

            'weightGrams' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'lengthMm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'widthMm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'heightMm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],

            'valueProps' => ['sometimes', 'nullable', 'array', 'max:12'],
            'valueProps.*.icon' => ['sometimes', 'nullable', 'string', 'max:40'],
            'valueProps.*.title' => ['required', 'string', 'max:120'],
            'valueProps.*.body' => ['sometimes', 'nullable', 'string', 'max:500'],

            'whatsIncluded' => ['sometimes', 'nullable', 'array', 'max:24'],
            'whatsIncluded.*' => ['string', 'max:160'],

            'shippingSummary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'returnsSummary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'warrantyText' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'returnsWindowDays' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],

            'badges' => ['sometimes', 'nullable', 'array', 'max:4'],
            'badges.*.label' => ['required', 'string', 'max:40'],
            'badges.*.tone' => ['sometimes', 'nullable', 'string', 'in:neutral,info,success,warning'],

            'primaryMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'mediaIds' => ['sometimes', 'array', 'max:24'],
            'mediaIds.*' => ['integer', 'exists:media,id'],

            'categoryIds' => ['sometimes', 'array', 'max:12'],
            'categoryIds.*' => ['integer', 'exists:categories,id'],

            'seoTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seoDescription' => ['sometimes', 'nullable', 'string', 'max:320'],
            'seoNoindex' => ['sometimes', 'boolean'],

            'publishedAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
