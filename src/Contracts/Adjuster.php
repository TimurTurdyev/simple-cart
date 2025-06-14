<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

use TimurTurdyev\SimpleCart\Support\Totals;

interface Adjuster
{
    public function name(): string;

    public function adjust(Totals $totals): Totals;

    public function toArray(): array;

    public static function fromArray(array $data): static;
}
