<?php

/**
 * HappyKoop application configuration.
 *
 * Everything here is deployment configuration, not store content. Store
 * content lives in the `settings` table and is edited by administrators.
 */
$list = static function (string $env, string $default = ''): array {
    return array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env($env, $default))
    ), static fn (string $value): bool => $value !== ''));
};

return [

    /*
     * Canonical storefront origin. Used for SEO defaults and payment
     * return URLs when no administrator value is stored.
     */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
     * Explicit browser origins allowed by CORS.
     */
    'frontend_urls' => $list('FRONTEND_URLS', 'http://localhost:3000,http://127.0.0.1:3000'),

    /*
     * Optional hostname allowlist constraining the admin-editable public URLs
     * (urls.api_base_url / urls.canonical_frontend_url). Empty means any
     * syntactically valid host is accepted.
     */
    'allowed_public_url_hosts' => $list('ALLOWED_PUBLIC_URL_HOSTS'),

    'tokens' => [
        'customer_minutes' => (int) env('SANCTUM_CUSTOMER_TOKEN_MINUTES', 43200), // 30 days
        'admin_minutes' => (int) env('SANCTUM_ADMIN_TOKEN_MINUTES', 720),         // 12 hours
    ],

    'checkout' => [
        'expires_after_minutes' => (int) env('CHECKOUT_EXPIRES_MINUTES', 60),
        'idempotency_ttl_hours' => (int) env('CHECKOUT_IDEMPOTENCY_TTL_HOURS', 24),
        'max_lines' => 50,
        'max_quantity_per_line' => 99,
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_image_bytes' => 8 * 1024 * 1024,
        'max_video_bytes' => 64 * 1024 * 1024,
        'max_dimension' => 8000,
        'thumbnail_width' => 480,
        'allow_svg' => (bool) env('MEDIA_ALLOW_SVG', false),
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
        'allowed_video_mimes' => ['video/mp4'],
    ],

    'payments' => [
        'stripe' => [
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'api_base' => 'https://api.stripe.com',
        ],
        'paypal' => [
            'sandbox_base' => 'https://api-m.sandbox.paypal.com',
            'live_base' => 'https://api-m.paypal.com',
        ],
        'http_timeout' => (int) env('PAYMENT_HTTP_TIMEOUT', 20),
    ],

    /*
     * Development seeder admin. Credentials come from the environment and are
     * never hardcoded in version control.
     */
    'seed' => [
        'admin_name' => env('SEED_ADMIN_NAME', 'Store Administrator'),
        'admin_email' => env('SEED_ADMIN_EMAIL'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
