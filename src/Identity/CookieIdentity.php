<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use Closure;
use Illuminate\Contracts\Cookie\QueueingFactory;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;

final class CookieIdentity implements CartIdentity
{
    private ?string $id = null;

    /**
     * @param Closure(): ?string $cookieValue Reads the request cookie lazily.
     */
    public function __construct(
        private readonly QueueingFactory $cookies,
        private readonly Closure $cookieValue,
        private readonly string $name,
        private readonly int $ttlMinutes,
    ) {
    }

    public function id(): string
    {
        return $this->id ??= ($this->cookieValue)() ?? bin2hex(random_bytes(16));
    }

    public function persist(): void
    {
        if ($this->hasQueued()) {
            return;
        }

        $this->cookies->queue($this->name, $this->id(), $this->ttlMinutes);
    }

    private function hasQueued(): bool
    {
        foreach ($this->cookies->getQueuedCookies() as $cookie) {
            if ($cookie->getName() === $this->name) {
                return true;
            }
        }

        return false;
    }
}
