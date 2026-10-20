<?php

declare(strict_types=1);

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Sanitizes administrator-authored HTML before it is STORED, so a stored XSS
 * payload never reaches the storefront even if a resource forgets to escape.
 */
final class Html
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return null;
        }

        return Purifier::clean($html);
    }

    /**
     * Plain text: all markup removed.
     */
    public static function text(?string $value, int $limit = 1000): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
