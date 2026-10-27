<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class MailSettingsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Omitted or empty => unchanged; clearResendApiKey removes it.
            'resendApiKey' => ['sometimes', 'nullable', 'string', 'max:200'],
            'clearResendApiKey' => ['sometimes', 'boolean'],
            'fromAddress' => ['sometimes', 'nullable', 'email', 'max:255'],
            'fromName' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
