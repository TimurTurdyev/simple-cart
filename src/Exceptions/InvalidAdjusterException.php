<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Exceptions;

final class InvalidAdjusterException extends CartException
{
    public static function invalidClass(string $class): self
    {
        return new self("Class [{$class}] does not implement the Adjuster contract.");
    }
}
