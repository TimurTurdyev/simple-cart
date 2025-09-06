<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class FixedDiscount implements Adjuster
{
    public function __construct(
        private string $name,
        private Price $amount,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function adjust(Totals $totals): Totals
    {
        return $totals->addDiscount($this->name, $this->amount);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => $this->amount->minor()];
    }

    public static function fromArray(array $data): static
    {
        foreach (['name', 'amount'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw InvalidAdjusterException::missingKey(self::class, $key);
            }
        }

        if (! is_string($data['name'])) {
            throw InvalidAdjusterException::invalidValue(self::class, 'name');
        }

        if (! is_int($data['amount'])) {
            throw InvalidAdjusterException::invalidValue(self::class, 'amount');
        }

        return new self($data['name'], Price::fromMinor($data['amount']));
    }
}
