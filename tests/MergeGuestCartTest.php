<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\StorageManager;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class MergeGuestCartTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('simple_cart.storage', 'database');
        $app['config']->set('simple_cart.identity.driver', 'session');
    }

    public function test_login_merges_guest_cart_into_user_cart(): void
    {
        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct(price: 1000), quantity: 2);

        $guestOwner = $this->app->make('session.store')->getId();
        $this->assertSame(1, CartRecord::query()->where('owner', $guestOwner)->count());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->active()->where('owner', $guestOwner)->count());
        $this->assertSame(ListStatus::Merged, CartRecord::query()->where('owner', $guestOwner)->value('status'));

        $payload = CartRecord::query()->where('owner', '42')->where('list', 'cart')->first()?->payload;

        $this->assertNotNull($payload);
        $this->assertSame(2, $payload['lines'][0]['quantity']);
    }

    public function test_login_is_silent_for_session_driver(): void
    {
        config()->set('simple_cart.storage', 'session');

        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_login_is_silent_for_custom_driver_without_merge_support(): void
    {
        config()->set('simple_cart.storage', 'custom');

        $custom = new class implements Storage
        {
            public array $records = [];

            public function read(string $list): array
            {
                return $this->records[$list] ?? [];
            }

            public function write(string $list, array $payload): void
            {
                $this->records[$list] = $payload;
            }

            public function forget(string $list): void
            {
                unset($this->records[$list]);
            }
        };

        $this->app->make(StorageManager::class)->extend('custom', fn (): Storage => $custom);
        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertNotSame([], $custom->records['cart']);
        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_merge_reaches_database_driver_through_cache_decorator(): void
    {
        config()->set('simple_cart.cache.enabled', true);

        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct(price: 1000), quantity: 2);

        $guestOwner = $this->app->make('session.store')->getId();

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->active()->where('owner', $guestOwner)->count());
        $this->assertSame(ListStatus::Merged, CartRecord::query()->where('owner', $guestOwner)->value('status'));
        $this->assertSame(1, CartRecord::query()->where('owner', '42')->count());

        $store = $this->app->make('cache')->store();

        $this->assertNull($store->get('simple_cart_'.$guestOwner));
        $this->assertNull($store->get('cart_42'));
    }

    public function test_merge_uses_cookie_identity_as_guest_owner(): void
    {
        config()->set('simple_cart.identity.driver', 'cookie');

        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct(price: 1000), quantity: 2);

        $guestOwner = $this->app->make('cookie')->queued('simple_cart_id')?->getValue();

        $this->assertNotNull($guestOwner);
        $this->assertSame(1, CartRecord::query()->where('owner', $guestOwner)->count());

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(0, CartRecord::query()->active()->where('owner', $guestOwner)->count());
        $this->assertSame(ListStatus::Merged, CartRecord::query()->where('owner', $guestOwner)->value('status'));
        $this->assertSame(2, CartRecord::query()->where('owner', '42')->first()?->payload['lines'][0]['quantity']);
    }

    #[DefineEnvironment('usesDisabledMerge')]
    public function test_disabled_merge_keeps_guest_cart(): void
    {
        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct());

        $guestOwner = $this->app->make('session.store')->getId();

        event(new Login('web', new GenericUser(['id' => 42]), false));

        $this->assertSame(1, CartRecord::query()->where('owner', $guestOwner)->count());
        $this->assertSame(0, CartRecord::query()->where('owner', '42')->count());
    }

    protected function usesDisabledMerge($app): void
    {
        $app['config']->set('simple_cart.merge.enabled', false);
    }
}
