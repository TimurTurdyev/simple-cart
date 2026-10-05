<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Events;

use TimurTurdyev\SimpleCart\Cart;

final readonly class ListCheckedOut
{
    public function __construct(
        public string $list,
        public ?string $reference,
        public Cart $snapshot,
    ) {
    }
}
