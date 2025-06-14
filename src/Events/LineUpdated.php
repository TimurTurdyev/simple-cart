<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

use TimurTurdyev\SimpleCart\Line;

final readonly class LineUpdated
{
    public function __construct(
        public string $list,
        public Line $line,
    ) {
    }
}
