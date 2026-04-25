<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

final class CartServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('database', config('simple_cart.storage'));
        $this->assertSame('cookie', config('simple_cart.identity.driver'));
        $this->assertSame('append', config('simple_cart.lists.cart.policy'));
        $this->assertSame('toggle', config('simple_cart.lists.wishlist.policy'));
        $this->assertSame(4, config('simple_cart.lists.compare.limit'));
        $this->assertTrue(config('simple_cart.events'));
    }

    public function test_package_migrations_are_registered_for_migrate(): void
    {
        $paths = array_map('realpath', $this->app->make('migrator')->paths());

        $this->assertContains(realpath(__DIR__.'/../database/migrations'), $paths);
    }

    public function test_prune_command_is_registered(): void
    {
        $this->artisan('list')->expectsOutputToContain('simple-cart:prune')->assertSuccessful();
    }
}
