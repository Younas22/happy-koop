<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class ProductSpecificationsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'specifications' => ['present', 'array', 'max:200'],
            'specifications.*.group' => ['sometimes', 'nullable', 'string', 'max:120'],
            'specifications.*.label' => ['required', 'string', 'max:160'],
            'specifications.*.value' => ['required', 'string', 'max:1000'],
            'specifications.*.unit' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
