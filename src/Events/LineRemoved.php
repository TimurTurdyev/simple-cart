<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Events;

use TimurTurdyev\Cart\Line;

final readonly class LineRemoved
{
    public function __construct(
        public string $list,
        public Line $line,
    ) {
    }
}
