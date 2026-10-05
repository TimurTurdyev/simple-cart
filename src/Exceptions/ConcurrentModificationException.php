<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class ConcurrentModificationException extends CartException
{
    public static function forList(string $list, int $attempts): self
    {
        return new self("List [{$list}] kept changing concurrently, gave up after {$attempts} attempts.");
    }
}
