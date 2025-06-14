<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use TimurTurdyev\SimpleCart\Contracts\CartIdentity;

final readonly class FixedIdentity implements CartIdentity
{
    public function __construct(
        private string $id,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function persist(): void
    {
    }
}
