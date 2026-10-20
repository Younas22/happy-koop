<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders the display string for a Money value WITHOUT the intl extension
 * (which is not installed on the target platform).
 *
 * Separators and symbol placement come from an explicit locale table; the
 * numeric part is built from integer string operations only.
 */
final class MoneyFormatter
{
    /**
     * @var array<string, array{decimal: string, group: string, position: string, space: bool}>
     */
    private const LOCALES = [
        'en-US' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
        'en-GB' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
        'en-CA' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
        'en-AU' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
        'de-DE' => ['decimal' => ',', 'group' => '.', 'position' => 'suffix', 'space' => true],
        'fr-FR' => ['decimal' => ',', 'group' => ' ', 'position' => 'suffix', 'space' => true],
        'nl-NL' => ['decimal' => ',', 'group' => '.', 'position' => 'prefix', 'space' => true],
        'es-ES' => ['decimal' => ',', 'group' => '.', 'position' => 'suffix', 'space' => true],
        'it-IT' => ['decimal' => ',', 'group' => '.', 'position' => 'suffix', 'space' => true],
        'sv-SE' => ['decimal' => ',', 'group' => ' ', 'position' => 'suffix', 'space' => true],
        'da-DK' => ['decimal' => ',', 'group' => '.', 'position' => 'suffix', 'space' => true],
        'nb-NO' => ['decimal' => ',', 'group' => ' ', 'position' => 'suffix', 'space' => true],
        'pl-PL' => ['decimal' => ',', 'group' => ' ', 'position' => 'suffix', 'space' => true],
        'ja-JP' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
        'ko-KR' => ['decimal' => '.', 'group' => ',', 'position' => 'prefix', 'space' => false],
    ];

    public const DEFAULT_LOCALE = 'en-US';

    /**
     * Container binding holding the store locale. Bound by the application
     * service provider so this class never depends on the settings layer
     * (which would create a bootstrap cycle during migrations and tests).
     */
    public const LOCALE_BINDING = 'happykoop.locale';

    /**
     * @return list<string>
     */
    public static function locales(): array
    {
        return array_keys(self::LOCALES);
    }

    public static function supportsLocale(?string $locale): bool
    {
        return $locale !== null && isset(self::LOCALES[$locale]);
    }

    public static function format(Money $money, ?string $locale = null): string
    {
        $rules = self::LOCALES[self::resolveLocale($locale)];

        $exponent = CurrencyRegistry::minorUnitExponent($money->currency);
        $symbol = CurrencyRegistry::symbol($money->currency);

        $negative = $money->amount < 0;
        $digits = (string) ($negative ? -$money->amount : $money->amount);

        if ($exponent > 0) {
            $digits = str_pad($digits, $exponent + 1, '0', STR_PAD_LEFT);
            $whole = substr($digits, 0, -$exponent);
            $fraction = substr($digits, -$exponent);
        } else {
            $whole = $digits;
            $fraction = '';
        }

        $whole = self::group($whole, $rules['group']);

        $number = $fraction === '' ? $whole : $whole.$rules['decimal'].$fraction;

        $gap = $rules['space'] ? "\u{00A0}" : '';

        $formatted = $rules['position'] === 'suffix'
            ? $number.$gap.$symbol
            : $symbol.$gap.$number;

        return ($negative ? '-' : '').$formatted;
    }

    private static function resolveLocale(?string $locale): string
    {
        if ($locale !== null && isset(self::LOCALES[$locale])) {
            return $locale;
        }

        $configured = app()->bound(self::LOCALE_BINDING) ? app(self::LOCALE_BINDING) : null;

        return is_string($configured) && isset(self::LOCALES[$configured])
            ? $configured
            : self::DEFAULT_LOCALE;
    }

    private static function group(string $whole, string $separator): string
    {
        if ($separator === '' || strlen($whole) <= 3) {
            return $whole;
        }

        $out = '';
        $len = strlen($whole);

        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && ($len - $i) % 3 === 0) {
                $out .= $separator;
            }
            $out .= $whole[$i];
        }

        return $out;
    }
}
