<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
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
        return new self($data['name'], (float) $data['percent']);
    }
}
