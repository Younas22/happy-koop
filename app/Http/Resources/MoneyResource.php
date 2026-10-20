<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\CurrencyRegistry;
use App\Support\Money;

/**
 * Single place that renders the contract's money object (api-contract.md 1.5):
 * {"amount": 2499, "currency": "USD", "formatted": "$24.99"}
 */
final class MoneyResource
{
    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public static function make(Money $money): array
    {
        return $money->toArray();
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}|null
     */
    public static function nullable(?Money $money): ?array
    {
        return $money?->toArray();
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public static function fromMinor(?int $amount, ?string $currency): array
    {
        return Money::of((int) $amount, CurrencyRegistry::normalize($currency))->toArray();
    }

    /**
     * Returns null when the raw amount is null. In this contract null means
     * "not yet determined" and must never be dressed up as a zero total.
     *
     * @return array{amount: int, currency: string, formatted: string}|null
     */
    public static function fromNullableMinor(?int $amount, ?string $currency): ?array
    {
        return $amount === null ? null : self::fromMinor($amount, $currency);
    }
}
