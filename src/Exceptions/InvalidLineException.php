<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Exceptions;

final class InvalidLineException extends CartException
{
    public static function emptyName(): self
    {
        return new self('Line name cannot be empty.');
    }

    public static function invalidQuantity(int $quantity): self
    {
        return new self("Line quantity must be at least 1, [{$quantity}] given.");
    }
}
