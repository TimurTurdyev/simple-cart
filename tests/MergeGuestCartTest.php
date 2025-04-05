<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use TimurTurdyev\Cart\CartManager;
use TimurTurdyev\Cart\Storage\CartRecord;
use TimurTurdyev\Cart\Tests\Fixtures\FakeProduct;

final class MergeGuestCartTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cart.storage', 'database');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_login_merges_guest_cart_into_user_cart(): void
    {
        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct(price: 1000), quantity: 2);

        $guestOwner = $this->app->make('session.store')->getId();
        $this->assertSame(1, CartRecord::query()->where('owner', $guestOwner)->count());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->where('owner', $guestOwner)->count());

        $payload = CartRecord::query()->where('owner', '42')->where('list', 'cart')->first()?->payload;

        $this->assertNotNull($payload);
        $this->assertSame(2, $payload['lines'][0]['quantity']);
    }

    public function test_login_is_silent_for_session_driver(): void
    {
        config()->set('cart.storage', 'session');

        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->count());
    }
}
