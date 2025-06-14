<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class UnknownListException extends CartException
{
    public static function forName(string $name): self
    {
        return new self("List [{$name}] is not configured. Add it to the [cart.lists] config.");
    }
}
