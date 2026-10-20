<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class RefundRequest extends ApiRequest
{
    /**
     * `amount` is minor units and is validated against the order's refundable
     * balance server-side.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
