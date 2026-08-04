<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Support;

use TimurTurdyev\SimpleCart\Line;
use WeakMap;

final class ModelCache
{
    /**
     * @var WeakMap<Line, object|null>|null
     */
    private static ?WeakMap $models = null;

    public static function has(Line $line): bool
    {
        return self::map()->offsetExists($line);
    }

    public static function get(Line $line): ?object
    {
        return self::map()[$line] ?? null;
    }

    public static function put(Line $line, ?object $model): void
    {
        self::map()[$line] = $model;
    }

    private static function map(): WeakMap
    {
        return self::$models ??= new WeakMap();
    }
}
