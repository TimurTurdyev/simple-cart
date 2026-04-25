<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

final readonly class ListAttributesUpdated
{
    /**
     * @param list<string> $changed
     */
    public function __construct(
        public string $list,
        public array $changed,
    ) {
    }
}
