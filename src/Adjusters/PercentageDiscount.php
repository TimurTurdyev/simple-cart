<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Adjusters;

use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Support\AdjusterPayload;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class PercentageDiscount implements Adjuster
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
        return $totals->addDiscount($this->name, $totals->total()->percentage($this->percent));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'percent' => $this->percent];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            AdjusterPayload::string(self::class, $data, 'name'),
            AdjusterPayload::numeric(self::class, $data, 'percent'),
        );
    }
}
