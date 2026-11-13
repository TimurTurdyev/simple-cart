<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

use TimurTurdyev\SimpleCart\Line;

final readonly class LineMetaUpdated
{
    /**
     * @param list<string|int> $changed
     */
    public function __construct(
        public string $list,
        public Line $line,
        public array $changed,
    ) {
    }
}
