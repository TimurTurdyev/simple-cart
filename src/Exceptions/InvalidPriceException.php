<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class InvalidPriceException extends CartException
{
    public static function unparsable(string $value): self
    {
        return new self("Unable to parse price value [{$value}].");
    }
}
