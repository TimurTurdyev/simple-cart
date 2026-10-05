<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class InvalidAttributeException extends CartException
{
    public static function invalidKey(): self
    {
        return new self('List attribute keys must be non-empty strings.');
    }

    public static function invalidValue(string $key): self
    {
        return new self("List attribute [{$key}] must hold a scalar, null or an array of those.");
    }

    public static function malformed(): self
    {
        return new self('List attributes payload must be an array.');
    }
}
