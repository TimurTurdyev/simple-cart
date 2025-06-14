<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

final readonly class ListCleared
{
    public function __construct(
        public string $list,
    ) {
    }
}
