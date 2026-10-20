<?php

/**
 * CORS — api-contract.md section 10.
 *
 * `allowed_origins` is an explicit list parsed from FRONTEND_URLS. A wildcard
 * is never used, and credentials are off in the default bearer topology
 * (authenticated calls originate from the Next.js server, not the browser).
 */
$origins = array_values(array_filter(array_map(
    static function (string $origin): string {
        return rtrim(trim($origin), '/');
    },
    explode(',', (string) env('FRONTEND_URLS', 'http://localhost:3000,http://127.0.0.1:3000'))
), static fn (string $origin): bool => $origin !== ''));

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Retry-After',
    ],

    'max_age' => 600,

    /*
     * True only in the Sanctum-cookie topology (api-contract.md 2.4). In the
     * default bearer topology no cross-site cookie is required.
     */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
