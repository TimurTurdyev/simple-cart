<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Adjusters;

use TimurTurdyev\Cart\Contracts\Adjuster;
use TimurTurdyev\Cart\Support\Totals;

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
        return new self($data['name'], (float) $data['percent']);
    }
}
