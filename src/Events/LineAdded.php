<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

use TimurTurdyev\SimpleCart\Line;

final readonly class LineAdded
{
    public function __construct(
        public string $list,
        public Line $line,
    ) {
    }
}
