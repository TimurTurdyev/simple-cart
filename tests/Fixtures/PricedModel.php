<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Fixtures;

use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Support\Price;

final class PricedModel implements Purchasable
{
    /**
     * @var array<string|int, int>
     */
    public static array $prices = [];

    public function __construct(public string|int $id)
    {
    }

    public static function query(): object
    {
        return new class
        {
            /**
             * @param array<string|int> $ids
             * @return array<PricedModel>
             */
            public function findMany(array $ids): array
            {
                return array_map(fn (string|int $id): PricedModel => new PricedModel($id), $ids);
            }
        };
    }

    public function cartId(): string|int
    {
        return $this->id;
    }

    public function cartName(): string
    {
        return 'Priced '.$this->id;
    }

    public function cartPrice(): Price
    {
        return Price::fromMinor(self::$prices[$this->id] ?? 0);
    }
}
