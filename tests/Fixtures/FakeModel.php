<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Fixtures;

final class FakeModel
{
    public function __construct(public string|int $id = 0)
    {
    }

    public static function query(): object
    {
        return new class
        {
            public function find(string|int $id): FakeModel
            {
                return new FakeModel($id);
            }
        };
    }
}
