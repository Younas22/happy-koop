<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class ProductMediaRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mediaIds' => ['required', 'array', 'min:1', 'max:24'],
            'mediaIds.*' => ['integer', 'exists:media,id'],
            'primaryMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
        ];
    }
}
