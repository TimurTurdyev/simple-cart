<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Support;

use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;

final class AdjusterPayload
{
    public static function int(string $class, array $data, string $key): int
    {
        $value = self::required($class, $data, $key);

        if (! is_int($value)) {
            throw InvalidAdjusterException::invalidValue($class, $key);
        }

        return $value;
    }

    public static function numeric(string $class, array $data, string $key): float
    {
        $value = self::required($class, $data, $key);

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidAdjusterException::invalidValue($class, $key);
        }

        return (float) $value;
    }

    public static function string(string $class, array $data, string $key): string
    {
        $value = self::required($class, $data, $key);

        if (! is_string($value)) {
            throw InvalidAdjusterException::invalidValue($class, $key);
        }

        return $value;
    }

    public static function stringOr(string $class, array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        if (! is_string($value)) {
            throw InvalidAdjusterException::invalidValue($class, $key);
        }

        return $value;
    }

    private static function required(string $class, array $data, string $key): mixed
    {
        if (! array_key_exists($key, $data)) {
            throw InvalidAdjusterException::missingKey($class, $key);
        }

        return $data[$key];
    }
}
