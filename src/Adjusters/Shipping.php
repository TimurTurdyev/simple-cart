<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class Shipping implements Adjuster
{
    public function __construct(
        private Price $amount,
        private string $name = 'shipping',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function adjust(Totals $totals): Totals
    {
        return $totals->addFee($this->name, $this->amount);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'amount' => $this->amount->minor()];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('amount', $data)) {
            throw InvalidAdjusterException::missingKey(self::class, 'amount');
        }

        if (! is_int($data['amount'])) {
            throw InvalidAdjusterException::invalidValue(self::class, 'amount');
        }

        $name = $data['name'] ?? 'shipping';

        if (! is_string($name)) {
            throw InvalidAdjusterException::invalidValue(self::class, 'name');
        }

        return new self(Price::fromMinor($data['amount']), $name);
    }
}
