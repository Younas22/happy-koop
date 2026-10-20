<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ISO 4217 subset supported by the store.
 *
 * The `intl` extension is intentionally NOT used (it is not installed on the
 * target platform), so every piece of currency metadata the application needs
 * is declared here explicitly.
 */
final class CurrencyRegistry
{
    /**
     * @var array<string, array{symbol: string, exponent: int, name: string}>
     */
    private const CURRENCIES = [
        'USD' => ['symbol' => '$', 'exponent' => 2, 'name' => 'US Dollar'],
        'EUR' => ['symbol' => '€', 'exponent' => 2, 'name' => 'Euro'],
        'GBP' => ['symbol' => '£', 'exponent' => 2, 'name' => 'Pound Sterling'],
        'CAD' => ['symbol' => 'CA$', 'exponent' => 2, 'name' => 'Canadian Dollar'],
        'AUD' => ['symbol' => 'A$', 'exponent' => 2, 'name' => 'Australian Dollar'],
        'CHF' => ['symbol' => 'CHF', 'exponent' => 2, 'name' => 'Swiss Franc'],
        'SEK' => ['symbol' => 'kr', 'exponent' => 2, 'name' => 'Swedish Krona'],
        'NOK' => ['symbol' => 'kr', 'exponent' => 2, 'name' => 'Norwegian Krone'],
        'DKK' => ['symbol' => 'kr', 'exponent' => 2, 'name' => 'Danish Krone'],
        'PLN' => ['symbol' => 'zł', 'exponent' => 2, 'name' => 'Polish Zloty'],
        'JPY' => ['symbol' => '¥', 'exponent' => 0, 'name' => 'Japanese Yen'],
        'KRW' => ['symbol' => '₩', 'exponent' => 0, 'name' => 'South Korean Won'],
    ];

    public const DEFAULT_CURRENCY = 'USD';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && isset(self::CURRENCIES[strtoupper($code)]);
    }

    public static function normalize(?string $code): string
    {
        $code = strtoupper((string) $code);

        return isset(self::CURRENCIES[$code]) ? $code : self::DEFAULT_CURRENCY;
    }

    public static function symbol(string $code): string
    {
        return self::CURRENCIES[self::normalize($code)]['symbol'];
    }

    public static function minorUnitExponent(string $code): int
    {
        return self::CURRENCIES[self::normalize($code)]['exponent'];
    }

    public static function name(string $code): string
    {
        return self::CURRENCIES[self::normalize($code)]['name'];
    }

    /**
     * Smallest step, in minor units, of one major unit. 100 for USD, 1 for JPY.
     */
    public static function majorUnitFactor(string $code): int
    {
        return (int) (10 ** self::minorUnitExponent($code));
    }
}
