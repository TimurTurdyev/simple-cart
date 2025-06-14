<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

final class ListLimitException extends CartException
{
    public static function reached(int $limit): self
    {
        return new self("List limit of [{$limit}] lines reached.");
    }
}
