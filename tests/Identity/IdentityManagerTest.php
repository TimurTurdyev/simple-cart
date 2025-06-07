<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Identity;

use InvalidArgumentException;
use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Identity\CookieIdentity;
use TimurTurdyev\Cart\Identity\IdentityManager;
use TimurTurdyev\Cart\Identity\SessionIdentity;
use TimurTurdyev\Cart\Tests\TestCase;

final class IdentityManagerTest extends TestCase
{
    public function test_default_driver_is_session(): void
    {
        $manager = $this->app->make(IdentityManager::class);

        $this->assertInstanceOf(SessionIdentity::class, $manager->driver());
    }

    public function test_identity_contract_resolves_default_driver(): void
    {
        $this->assertInstanceOf(SessionIdentity::class, $this->app->make(CartIdentity::class));
    }

    public function test_cookie_driver_from_config(): void
    {
        config()->set('cart.identity.driver', 'cookie');

        $manager = $this->app->make(IdentityManager::class);

        $this->assertInstanceOf(CookieIdentity::class, $manager->driver());
    }

    public function test_custom_driver_via_extend(): void
    {
        $custom = new class implements CartIdentity
        {
            public function id(): string
            {
                return 'custom-id';
            }

            public function persist(): void
            {
            }
        };

        config()->set('cart.identity.driver', 'custom');

        $manager = $this->app->make(IdentityManager::class);
        $manager->extend('custom', fn (): CartIdentity => $custom);

        $this->assertSame($custom, $manager->driver());
        $this->assertSame('custom-id', $this->app->make(CartIdentity::class)->id());
    }

    public function test_unknown_driver_fails(): void
    {
        config()->set('cart.identity.driver', 'missing');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(IdentityManager::class)->driver();
    }
}
