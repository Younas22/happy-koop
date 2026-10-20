<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiRequest;

class PayPalCaptureRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'orderNumber' => ['required', 'string', 'max:64'],
            'accessToken' => ['sometimes', 'nullable', 'string', 'max:128'],
            'paypalOrderId' => ['required', 'string', 'max:64'],
        ];
    }
}
