<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Html;

/**
 * Contact form submission.
 *
 * Everything is validated server-side, including the one field a human never
 * fills in. The visitor cannot choose a recipient: there is no such field, and
 * the administrator's address is resolved from store settings - which is what
 * stops the form from becoming a relay to any address an attacker supplies.
 */
class ContactRequest extends ApiRequest
{
    /**
     * Sanitize BEFORE validating, not after.
     *
     * `Html::text()` strips tags, so a "message" of `<div></div>` collapses to
     * nothing. Sanitizing first means the length rules below judge what will
     * actually be stored, and such a submission gets an honest 422 instead of
     * reaching a NOT NULL column as null.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Html::text($this->input('name'), 160),
            'subject' => Html::text($this->input('subject'), 180),
            'message' => Html::text($this->input('message'), 5000),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],

            // Honeypot. Hidden from people, irresistible to naive bots; a
            // non-empty value is a validation failure, not a silent drop, so
            // the behaviour is honest and testable.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'This submission was rejected.',
            'message.min' => 'Please describe your question in at least 10 characters.',
        ];
    }
}
