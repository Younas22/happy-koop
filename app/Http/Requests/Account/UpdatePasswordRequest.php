<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'passwordConfirmation' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'passwordConfirmation.same' => 'The password confirmation does not match.',
        ];
    }
}
