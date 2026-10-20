<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'email' => [
                $creating ? 'required' : 'sometimes', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'password' => [
                $creating ? 'required' : 'sometimes',
                'string', Password::min(8)->letters()->numbers(),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'roleSlugs' => ['sometimes', 'array', 'max:6'],
            'roleSlugs.*' => ['string', 'exists:roles,slug'],
        ];
    }
}
