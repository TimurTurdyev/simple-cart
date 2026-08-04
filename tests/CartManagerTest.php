<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Exceptions\UnknownListException;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class CartManagerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
    }

    public function test_resolves_configured_lists(): void
    {
        $manager = $this->app->make(CartManager::class);

        $this->assertSame('cart', $manager->list()->name());
        $this->assertSame('wishlist', $manager->list('wishlist')->name());
    }

    public function test_instances_are_cached(): void
    {
        $manager = $this->app->make(CartManager::class);

        $this->assertSame($manager->list('cart'), $manager->list('cart'));
    }

    public function test_unknown_list_fails(): void
    {
        $this->expectException(UnknownListException::class);

        $this->app->make(CartManager::class)->list('missing');
    }

    public function test_custom_list_from_config(): void
    {
        config()->set('cart.lists.viewed', ['policy' => 'toggle', 'limit' => 20]);

        $list = $this->app->make(CartManager::class)->list('viewed');

        $this->assertSame('viewed', $list->name());
        $this->assertTrue($list->toggle(new FakeProduct()));
        $this->assertFalse($list->toggle(new FakeProduct()));
    }

    public function test_state_persists_between_manager_instances(): void
    {
        $first = $this->app->make(CartManager::class);
        $first->list()->add(new FakeProduct(id: 1, price: 1000), quantity: 2);

        $second = new CartManager(
            storage: $this->app->make(\TimurTurdyev\SimpleCart\Contracts\Storage::class),
            config: $this->app->make('config'),
        );

        $this->assertSame(2000, $second->list()->subtotal()->minor());
    }
}
