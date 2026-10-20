<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiRequest;

class UpdateProfileRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'acceptsMarketing' => ['sometimes', 'boolean'],
        ];
    }
}
