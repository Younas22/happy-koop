<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Http\Requests\ApiRequest;
use App\Services\Settings\PaymentSettingsService;

class CheckoutInitializeRequest extends ApiRequest
{
    /**
     * The request carries NO prices. Any price-like field a client sends is
     * simply not read (api-contract.md 5).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.config('happykoop.checkout.max_lines', 50)],
            'items.*.productId' => ['required', 'integer', 'min:1'],
            'items.*.variantId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'items.*.quantity' => [
                'required', 'integer', 'min:1',
                'max:'.config('happykoop.checkout.max_quantity_per_line', 99),
            ],
            'cartHash' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'shippingAddress' => ['required', 'array'],
            'shippingAddress.firstName' => ['required', 'string', 'max:120'],
            'shippingAddress.lastName' => ['required', 'string', 'max:120'],
            'shippingAddress.company' => ['sometimes', 'nullable', 'string', 'max:160'],
            'shippingAddress.line1' => ['required', 'string', 'max:255'],
            'shippingAddress.line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shippingAddress.city' => ['required', 'string', 'max:120'],
            'shippingAddress.region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'shippingAddress.postalCode' => ['required', 'string', 'max:32'],
            'shippingAddress.countryCode' => ['required', 'string', 'size:2', 'alpha'],
            'shippingAddress.phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'billingAddress' => ['sometimes', 'nullable', 'array'],
            'billingAddress.firstName' => ['required_with:billingAddress', 'string', 'max:120'],
            'billingAddress.lastName' => ['required_with:billingAddress', 'string', 'max:120'],
            'billingAddress.company' => ['sometimes', 'nullable', 'string', 'max:160'],
            'billingAddress.line1' => ['required_with:billingAddress', 'string', 'max:255'],
            'billingAddress.line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billingAddress.city' => ['required_with:billingAddress', 'string', 'max:120'],
            'billingAddress.region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'billingAddress.postalCode' => ['required_with:billingAddress', 'string', 'max:32'],
            'billingAddress.countryCode' => ['required_with:billingAddress', 'string', 'size:2', 'alpha'],
            'billingAddress.phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'shippingMethodId' => ['required', 'string', 'max:64'],
            'couponCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'paymentProvider' => ['required', 'string', 'in:'.implode(',', PaymentSettingsService::PROVIDERS)],
            'customerNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'acceptsMarketing' => ['sometimes', 'boolean'],
        ];
    }
}
