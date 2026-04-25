<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Identity;

use InvalidArgumentException;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Identity\CookieIdentity;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;
use TimurTurdyev\SimpleCart\Identity\SessionIdentity;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class IdentityManagerTest extends TestCase
{
    public function test_default_driver_is_cookie(): void
    {
        $manager = $this->app->make(IdentityManager::class);

        $this->assertInstanceOf(CookieIdentity::class, $manager->driver());
    }

    public function test_identity_contract_resolves_default_driver(): void
    {
        $this->assertInstanceOf(CookieIdentity::class, $this->app->make(CartIdentity::class));
    }

    public function test_session_driver_from_config(): void
    {
        config()->set('simple_cart.identity.driver', 'session');

        $this->assertInstanceOf(SessionIdentity::class, $this->app->make(IdentityManager::class)->driver());
    }

    public function test_cookie_driver_from_config(): void
    {
        config()->set('simple_cart.identity.driver', 'cookie');

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

        config()->set('simple_cart.identity.driver', 'custom');

        $manager = $this->app->make(IdentityManager::class);
        $manager->extend('custom', fn (): CartIdentity => $custom);

        $this->assertSame($custom, $manager->driver());
        $this->assertSame('custom-id', $this->app->make(CartIdentity::class)->id());
    }

    public function test_unknown_driver_fails(): void
    {
        config()->set('simple_cart.identity.driver', 'missing');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(IdentityManager::class)->driver();
    }
}
