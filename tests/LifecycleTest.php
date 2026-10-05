<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Events\ListCheckedOut;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\CachedStorage;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Storage\SessionStorage;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class LifecycleTest extends TestCase
{
    public function test_checkout_keeps_record_as_ordered(): void
    {
        Event::fake([ListCheckedOut::class]);
        $cart = $this->cart();
        $cart->add(new FakeProduct(price: 1000), quantity: 2);
        $cart->setAttribute('contact', 'ann@example.com');

        $snapshot = $cart->checkout('ORD-1');

        $record = CartRecord::query()->sole();
        $this->assertSame(ListStatus::Ordered, $record->status);
        $this->assertSame('ORD-1', $record->reference);
        $this->assertNotNull($record->status_changed_at);
        $this->assertSame((string) $record->id, $record->slot);
        $this->assertSame(2000, $snapshot->subtotal()->minor());
        $this->assertSame(['contact' => 'ann@example.com'], $snapshot->attributes);
        $this->assertTrue($cart->isEmpty());
        $this->assertTrue($this->cart()->isEmpty());
        Event::assertDispatched(ListCheckedOut::class, fn (ListCheckedOut $e): bool => $e->reference === 'ORD-1' && $e->snapshot->count() === 1);
    }

    public function test_next_write_starts_new_active_list_for_same_owner(): void
    {
        $cart = $this->cart();
        $cart->add(new FakeProduct(id: 1));
        $cart->checkout('ORD-1');

        $cart->add(new FakeProduct(id: 2));

        $this->assertSame(1, CartRecord::query()->active()->count());
        $this->assertSame(1, CartRecord::query()->status(ListStatus::Ordered)->count());
        $this->assertSame(1, $this->cart()->count());
    }

    public function test_snapshot_comes_from_the_closed_record_not_from_memory(): void
    {
        $checkoutTab = $this->cart();
        $checkoutTab->add(new FakeProduct(id: 1));

        $this->cart()->add(new FakeProduct(id: 2));

        $this->assertSame(2, $checkoutTab->checkout('ORD-1')->count());
        $this->assertSame(0, CartRecord::query()->active()->count());
    }

    public function test_checkout_of_empty_list_is_a_noop(): void
    {
        Event::fake([ListCheckedOut::class]);

        $snapshot = $this->cart()->checkout('ORD-1');

        $this->assertTrue($snapshot->isEmpty());
        $this->assertSame(0, CartRecord::query()->count());
        Event::assertNotDispatched(ListCheckedOut::class);
    }

    public function test_checkout_through_cache_drops_cached_list(): void
    {
        $storage = new CachedStorage(
            inner: new DatabaseStorage(new FixedIdentity('guest-1')),
            identity: new FixedIdentity('guest-1'),
            cache: $this->app->make('cache')->store('array'),
            ttlMinutes: 60,
            prefix: 'simple_cart_',
        );
        $cart = $this->cart($storage);
        $cart->add(new FakeProduct());
        $this->cart($storage)->count();

        $cart->checkout('ORD-1');

        $this->assertTrue($this->cart($storage)->isEmpty());
        $this->assertSame(ListStatus::Ordered, CartRecord::query()->value('status'));
    }

    public function test_session_storage_falls_back_to_forget(): void
    {
        Event::fake([ListCheckedOut::class]);
        $cart = $this->cart(new SessionStorage($this->app->make('session.store')));
        $cart->add(new FakeProduct(price: 500));

        $snapshot = $cart->checkout('ORD-1');

        $this->assertSame(500, $snapshot->subtotal()->minor());
        $this->assertFalse(session()->has('simple_cart.cart'));
        Event::assertDispatched(ListCheckedOut::class);
    }

    private function cart(?Storage $storage = null): ManagedList
    {
        return (new CartManager(
            storage: $storage ?? new DatabaseStorage(new FixedIdentity('guest-1')),
            config: $this->app['config'],
            events: $this->app['events'],
        ))->list('cart');
    }
}
