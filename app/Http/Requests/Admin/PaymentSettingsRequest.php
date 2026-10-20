<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

/**
 * Secret fields are `present`-optional on purpose: an omitted or empty-string
 * value means "leave unchanged", an explicit null clears it
 * (api-contract.md 8.2). Validation messages never echo a submitted value.
 */
class PaymentSettingsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmLiveMode' => ['sometimes', 'boolean'],

            'stripe' => ['sometimes', 'array'],
            'stripe.enabled' => ['sometimes', 'boolean'],
            'stripe.mode' => ['sometimes', 'string', 'in:test,live'],
            'stripe.publishableKey' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stripe.secretKey' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stripe.webhookSecret' => ['sometimes', 'nullable', 'string', 'max:255'],

            'paypal' => ['sometimes', 'array'],
            'paypal.enabled' => ['sometimes', 'boolean'],
            'paypal.mode' => ['sometimes', 'string', 'in:sandbox,live'],
            'paypal.clientId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paypal.clientSecret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paypal.webhookId' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stripe.secretKey' => 'Stripe secret key',
            'stripe.webhookSecret' => 'Stripe webhook secret',
            'paypal.clientSecret' => 'PayPal client secret',
            'paypal.webhookId' => 'PayPal webhook id',
        ];
    }
}
