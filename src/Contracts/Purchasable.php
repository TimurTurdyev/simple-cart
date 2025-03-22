<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Contracts;

use TimurTurdyev\Cart\Support\Price;

interface Purchasable
{
    public function cartId(): string|int;

    public function cartName(): string;

    public function cartPrice(): Price;
}
