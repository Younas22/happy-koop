<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\PublicUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Administrator-editable public URL: absolute, http/https, no credentials,
 * valid host, and on the server-side host allowlist when one is configured
 * (api-contract.md 8.1).
 */
class PublicUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a URL.');

            return;
        }

        [$valid, $reason] = PublicUrl::validate($value);

        if (! $valid) {
            $fail($reason ?? 'The :attribute is not a valid URL.');
        }
    }
}
