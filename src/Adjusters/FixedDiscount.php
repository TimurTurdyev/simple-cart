<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Support\AdjusterPayload;
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
        return new self(
            AdjusterPayload::string(self::class, $data, 'name'),
            Price::fromMinor(AdjusterPayload::int(self::class, $data, 'amount')),
        );
    }
}
