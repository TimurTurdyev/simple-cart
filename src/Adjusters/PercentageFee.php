<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class PercentageFee implements Adjuster
{
    public function __construct(
        private string $name,
        private float $percent,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function adjust(Totals $totals): Totals
    {
        return $totals->addFee($this->name, $totals->total()->percentage($this->percent));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'percent' => $this->percent];
    }

    public static function fromArray(array $data): static
    {
        foreach (['name', 'percent'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw InvalidAdjusterException::missingKey(self::class, $key);
            }
        }

        if (! is_string($data['name'])) {
            throw InvalidAdjusterException::invalidValue(self::class, 'name');
        }

        if (! is_int($data['percent']) && ! is_float($data['percent'])) {
            throw InvalidAdjusterException::invalidValue(self::class, 'percent');
        }

        return new self($data['name'], (float) $data['percent']);
    }
}
