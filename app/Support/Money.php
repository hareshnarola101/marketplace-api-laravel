<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * An amount in minor units (cents). All arithmetic stays in integers so
 * totals never drift the way floats do. Assumes two-decimal currencies.
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public static function of(int $amount, string $currency): self
    {
        if (! preg_match('/^[A-Za-z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}].");
        }

        return new self($amount, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
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

    public function times(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /** Decimal string for display, e.g. "-12.05". */
    public function format(): string
    {
        $absolute = abs($this->amount);

        return sprintf('%s%d.%02d', $this->amount < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }

    /** @return array{amount: int, currency: string, formatted: string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    /** @return array{amount: int, currency: string, formatted: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
