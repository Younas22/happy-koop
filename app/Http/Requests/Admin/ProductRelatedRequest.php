<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class ProductRelatedRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'productIds' => ['present', 'array', 'max:12'],
            'productIds.*' => ['integer', 'exists:products,id'],
        ];
    }
}
