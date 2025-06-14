<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use Closure;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;

final readonly class AuthAwareIdentity implements CartIdentity
{
    /**
     * @param Closure(): (int|string|null) $authId
     */
    public function __construct(
        private Closure $authId,
        private CartIdentity $guest,
    ) {
    }

    public function id(): string
    {
        $authId = ($this->authId)();

        return $authId === null ? $this->guest->id() : (string) $authId;
    }

    public function persist(): void
    {
        if (($this->authId)() === null) {
            $this->guest->persist();
        }
    }
}
