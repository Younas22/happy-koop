<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

/**
 * Base request for every API endpoint.
 *
 * Guarantees the contract's 422 shape: keys in `errors` are camelCase field
 * paths that match the JSON request body (api-contract.md 1.3), with numeric
 * list indexes preserved (`items.0.quantity`).
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'code' => 'VALIDATION_FAILED',
            'errors' => self::camelCaseKeys($validator->errors()->messages()),
        ], 422));
    }

    /**
     * @param  array<string, list<string>>  $messages
     * @return array<string, list<string>>
     */
    public static function camelCaseKeys(array $messages): array
    {
        $out = [];

        foreach ($messages as $key => $value) {
            $out[self::camelCasePath((string) $key)] = $value;
        }

        return $out;
    }

    public static function camelCasePath(string $key): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => is_numeric($segment) || $segment === '*'
                ? $segment
                : Str::camel($segment),
            explode('.', $key)
        ));
    }

    /**
     * Convenience accessor returning a trimmed string or null for blank input.
     */
    protected function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
