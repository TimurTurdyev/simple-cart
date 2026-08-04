<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Contracts;

interface CartIdentity
{
    public function id(): string;

    public function persist(): void;
}
