<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Events;

final readonly class ListCleared
{
    public function __construct(
        public string $list,
    ) {
    }
}
