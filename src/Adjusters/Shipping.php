<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Adjusters;

use TimurTurdyev\Cart\Contracts\Adjuster;
use TimurTurdyev\Cart\Support\Price;
use TimurTurdyev\Cart\Support\Totals;

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
        return new self(Price::fromMinor($data['amount']), $data['name']);
    }
}
