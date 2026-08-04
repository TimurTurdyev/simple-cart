<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Fixtures;

final class FakeModel
{
    public static int $queries = 0;

    public function __construct(public string|int $id = 0)
    {
    }

    public static function resetQueries(): void
    {
        self::$queries = 0;
    }

    public static function query(): object
    {
        return new class
        {
            public function find(string|int $id): FakeModel
            {
                FakeModel::$queries++;

                return new FakeModel($id);
            }

            /**
             * @param array<string|int> $ids
             * @return array<FakeModel>
             */
            public function findMany(array $ids): array
            {
                FakeModel::$queries++;

                return array_map(fn (string|int $id): FakeModel => new FakeModel($id), $ids);
            }
        };
    }
}
