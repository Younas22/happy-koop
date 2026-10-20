<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable money value object.
 *
 * `amount` is always an INTEGER number of minor units (cents). No float
 * arithmetic and no rounding of money happens anywhere in this class.
 */
final class Money implements JsonSerializable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
        if (! CurrencyRegistry::supports($currency)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }
    }

    public static function of(int $amount, string $currency): self
    {
        return new self($amount, strtoupper($currency));
    }

    public static function zero(string $currency = CurrencyRegistry::DEFAULT_CURRENCY): self
    {
        return new self(0, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Integer percentage share, truncated towards zero. Used for percent
     * coupons so a discount can never produce a fractional minor unit.
     */
    public function percentage(int $percent): self
    {
        return new self(intdiv($this->amount * $percent, 100), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    /**
     * Clamp to zero so a discount can never produce a negative total.
     */
    public function atLeastZero(): self
    {
        return $this->amount < 0 ? self::zero($this->currency) : $this;
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount ? $this : $other;
    }

    public function formatted(?string $locale = null): string
    {
        return MoneyFormatter::format($this, $locale);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function toArray(?string $locale = null): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => MoneyFormatter::format($this, $locale),
        ];
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: [{$this->currency}] vs [{$other->currency}]."
            );
        }
    }
}
