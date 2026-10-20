<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validation and normalization for administrator-editable public URLs
 * (api-contract.md 8.1).
 *
 * Must be absolute, http/https only, carry no embedded credentials, have a
 * valid host, and — when ALLOWED_PUBLIC_URL_HOSTS is configured — a host on
 * that allowlist.
 */
final class PublicUrl
{
    /**
     * @return array{0: bool, 1: ?string} [valid, reason]
     */
    public static function validate(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return [false, 'The URL must not be empty.'];
        }

        if (preg_match('/\s/', $url) === 1) {
            return [false, 'The URL must not contain whitespace.'];
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return [false, 'The URL must be absolute and include a scheme and host.'];
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return [false, 'The URL scheme must be http or https.'];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return [false, 'The URL must not contain credentials.'];
        }

        $host = strtolower($parts['host']);

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isHostname = preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host) === 1;

        if (! $isIp && ! $isHostname) {
            return [false, 'The URL host is not a valid hostname or IP address.'];
        }

        $allowed = (array) config('happykoop.allowed_public_url_hosts', []);

        if ($allowed !== [] && ! in_array($host, array_map('strtolower', $allowed), true)) {
            return [false, 'The URL host is not in the server-side allowlist.'];
        }

        return [true, null];
    }

    public static function isValid(string $url): bool
    {
        return self::validate($url)[0];
    }

    /**
     * Normalizes the trailing slash away, preserving an explicit port and path.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        $normalized = rtrim($url, '/');

        return $normalized === '' ? $url : $normalized;
    }
}
