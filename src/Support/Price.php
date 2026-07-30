<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Support;

use TimurTurdyev\Cart\Exceptions\InvalidPriceException;

final readonly class Price
{
    private const int SUBUNIT = 100;

    private function __construct(public int $amount)
    {
    }

    public static function fromMinor(int $amount): self
    {
        return new self($amount);
    }

    public static function fromDecimal(float|string $value): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        return new self((int) round($value * self::SUBUNIT));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function minor(): int
    {
        return $this->amount;
    }

    public function decimal(): float
    {
        return $this->amount / self::SUBUNIT;
    }

    public function format(string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        return number_format($this->decimal(), 2, $decimalSeparator, $thousandsSeparator);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function times(int|float $factor): self
    {
        return new self((int) round($this->amount * $factor));
    }

    public function percentage(float $percent): self
    {
        return $this->times($percent / 100);
    }

    public function negate(): self
    {
        return new self(-$this->amount);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    private static function fromString(string $value): self
    {
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            throw InvalidPriceException::unparsable($value);
        }

        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $minor = ((int) $matches[2]) * self::SUBUNIT + (int) substr($fraction, 0, 2);

        if ($fraction[2] >= '5') {
            $minor++;
        }

        return new self($matches[1] === '-' ? -$minor : $minor);
    }
}
