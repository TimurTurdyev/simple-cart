<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use Closure;
use Illuminate\Contracts\Cookie\QueueingFactory;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Exceptions\InvalidConfigurationException;

final class CookieIdentity implements CartIdentity
{
    public const string DEFAULT_PATTERN = '/^[0-9a-f]{32}$/';

    private ?string $id = null;

    /**
     * @param Closure(): ?string $cookieValue Reads the request cookie lazily.
     * @param string $pattern Accepted cookie values; widen it to keep carts
     *                        of an older id format, e.g. uniqid('cart', true).
     */
    public function __construct(
        private readonly QueueingFactory $cookies,
        private readonly Closure $cookieValue,
        private readonly string $name,
        private readonly int $ttlMinutes,
        private readonly string $pattern = self::DEFAULT_PATTERN,
    ) {
        if (@preg_match($this->pattern, self::generate()) !== 1) {
            throw InvalidConfigurationException::invalidCookiePattern($this->pattern);
        }
    }

    public function id(): string
    {
        return $this->id ??= $this->readCookie() ?? self::generate();
    }

    public function persist(): void
    {
        if ($this->hasQueued()) {
            return;
        }

        $this->cookies->queue($this->name, $this->id(), $this->ttlMinutes);
    }

    private function readCookie(): ?string
    {
        $value = ($this->cookieValue)();

        return is_string($value) && preg_match($this->pattern, $value) === 1 ? $value : null;
    }

    private static function generate(): string
    {
        return bin2hex(random_bytes(16));
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
