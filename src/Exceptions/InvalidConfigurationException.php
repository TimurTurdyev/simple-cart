<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class InvalidConfigurationException extends CartException
{
    public static function invalidQuantityRule(string $reason): self
    {
        return new self("Invalid quantity rule: {$reason}.");
    }

    public static function invalidCookiePattern(string $pattern): self
    {
        return new self("Cookie id pattern [{$pattern}] must be a valid regular expression that accepts generated 32 hex ids.");
    }
}
