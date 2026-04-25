<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\Support\Price;

final readonly class LineRepriced
{
    public function __construct(
        public string $list,
        public Line $line,
        public Price $previous,
    ) {
    }
}
