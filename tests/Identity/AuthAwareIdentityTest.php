<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Identity;

use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Identity\AuthAwareIdentity;
use TimurTurdyev\Cart\Tests\TestCase;

final class AuthAwareIdentityTest extends TestCase
{
    public function test_guest_id_is_used_when_not_authenticated(): void
    {
        $identity = new AuthAwareIdentity(
            authId: fn (): int|string|null => null,
            guest: new SpyIdentity('guest-1'),
        );

        $this->assertSame('guest-1', $identity->id());
    }

    public function test_auth_id_wins_when_authenticated(): void
    {
        $identity = new AuthAwareIdentity(
            authId: fn (): int|string|null => 42,
            guest: new SpyIdentity('guest-1'),
        );

        $this->assertSame('42', $identity->id());
    }

    public function test_persist_delegates_to_guest_identity(): void
    {
        $guest = new SpyIdentity('guest-1');

        (new AuthAwareIdentity(fn (): int|string|null => null, $guest))->persist();

        $this->assertSame(1, $guest->persistCalls);
    }

    public function test_persist_is_noop_when_authenticated(): void
    {
        $guest = new SpyIdentity('guest-1');

        (new AuthAwareIdentity(fn (): int|string|null => 42, $guest))->persist();

        $this->assertSame(0, $guest->persistCalls);
    }
}

final class SpyIdentity implements CartIdentity
{
    public int $persistCalls = 0;

    public function __construct(private readonly string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function persist(): void
    {
        $this->persistCalls++;
    }
}
