<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Rules\PublicUrlRule;

class OrderFulfilmentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'carrier' => ['sometimes', 'nullable', 'string', 'max:120'],
            'trackingNumber' => ['sometimes', 'nullable', 'string', 'max:120'],
            'trackingUrl' => ['sometimes', 'nullable', 'string', 'max:1000', new PublicUrlRule()],
        ];
    }
}
