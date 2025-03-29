<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Exceptions;

final class UnknownLineException extends CartException
{
    public static function forId(string $id): self
    {
        return new self("Line [{$id}] not found in the list.");
    }
}
