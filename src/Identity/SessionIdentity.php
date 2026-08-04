<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Identity;

use Illuminate\Contracts\Session\Session;
use TimurTurdyev\Cart\Contracts\CartIdentity;

final readonly class SessionIdentity implements CartIdentity
{
    public function __construct(
        private Session $session,
    ) {
    }

    public function id(): string
    {
        return $this->session->getId();
    }

    public function persist(): void
    {
    }
}
