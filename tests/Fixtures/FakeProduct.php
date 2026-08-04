<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Fixtures;

use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Support\Price;

final readonly class FakeProduct implements Purchasable
{
    public function __construct(
        private int $id = 1,
        private string $name = 'Sample product',
        private int $price = 1999,
    ) {
    }

    public function cartId(): string|int
    {
        return $this->id;
    }

    public function cartName(): string
    {
        return $this->name;
    }

    public function cartPrice(): Price
    {
        return Price::fromMinor($this->price);
    }
}
