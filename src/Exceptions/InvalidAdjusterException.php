<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class InvalidAdjusterException extends CartException
{
    public static function invalidClass(string $class): self
    {
        return new self("Class [{$class}] does not implement the Adjuster contract.");
    }

    public static function missingKey(string $class, string $key): self
    {
        return new self("Adjuster [{$class}] payload is missing the [{$key}] key.");
    }

    public static function invalidValue(string $class, string $key): self
    {
        return new self("Adjuster [{$class}] payload has an invalid [{$key}] value.");
    }

    public static function malformedEntry(): self
    {
        return new self('Adjusters payload must be a list of arrays with class and data.');
    }
}
