<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\LineAdded;
use TimurTurdyev\SimpleCart\Events\LineRemoved;
use TimurTurdyev\SimpleCart\Events\LineUpdated;
use TimurTurdyev\SimpleCart\Events\ListCleared;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class CartEventsTest extends TestCase
{
    public function test_lifecycle_events(): void
    {
        Event::fake();

        $cart = $this->app->make(CartManager::class)->list('cart');
        $product = new FakeProduct();

        $line = $cart->add($product);
        $cart->setQuantity($line->id, 3);
        $cart->remove($line->id);
        $cart->clear();

        Event::assertDispatched(LineAdded::class, fn (LineAdded $e): bool => $e->list === 'cart');
        Event::assertDispatched(LineUpdated::class, fn (LineUpdated $e): bool => $e->line->quantity === 3);
        Event::assertDispatched(LineRemoved::class);
        Event::assertDispatched(ListCleared::class);
    }

    public function test_quantity_drop_to_zero_dispatches_removed(): void
    {
        Event::fake();

        $cart = $this->app->make(CartManager::class)->list('cart');
        $line = $cart->add(new FakeProduct());

        $cart->setQuantity($line->id, 0);

        Event::assertDispatched(LineRemoved::class);
        Event::assertNotDispatched(LineUpdated::class);
    }

    public function test_repeated_add_in_toggle_list_stays_silent(): void
    {
        Event::fake();

        $wishlist = $this->app->make(CartManager::class)->list('wishlist');
        $product = new FakeProduct();

        $wishlist->add($product);
        $wishlist->add($product);

        Event::assertDispatchedTimes(LineAdded::class, 1);
    }

    public function test_events_can_be_disabled(): void
    {
        config()->set('simple_cart.events', false);
        Event::fake();

        $this->app->forgetInstance(CartManager::class);
        $this->app->make(CartManager::class)->list('cart')->add(new FakeProduct());

        Event::assertNotDispatched(LineAdded::class);
    }
}
