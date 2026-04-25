<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Fixtures;

use TimurTurdyev\SimpleCart\Contracts\HasQuantityRule;
use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\QuantityRule;

final readonly class PackedProduct implements Purchasable, HasQuantityRule
{
    public function __construct(
        private int $id = 1,
        private int $min = 1,
        private int $step = 1,
        private ?int $max = null,
    ) {
    }

    public function cartId(): string|int
    {
        return $this->id;
    }

    public function cartName(): string
    {
        return 'Packed product';
    }

    public function cartPrice(): Price
    {
        return Price::fromMinor(100);
    }

    public function cartQuantityRule(): QuantityRule
    {
        return new QuantityRule($this->min, $this->step, $this->max);
    }
}
