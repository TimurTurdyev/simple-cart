<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

interface CartIdentity
{
    public function id(): string;

    public function persist(): void;
}
