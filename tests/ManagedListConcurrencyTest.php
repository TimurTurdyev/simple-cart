<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\LineAdded;
use TimurTurdyev\SimpleCart\Events\LineRemoved;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class ManagedListConcurrencyTest extends TestCase
{
    public function test_two_tabs_keep_both_added_lines(): void
    {
        $first = $this->tab();
        $second = $this->tab();

        $this->assertSame(0, $first->count());
        $this->assertSame(0, $second->count());

        $first->add(new FakeProduct(id: 1));
        $second->add(new FakeProduct(id: 2));

        $this->assertSame(2, $this->tab()->count());
        $this->assertSame(2, $second->count());
    }

    public function test_double_click_adds_quantity_twice(): void
    {
        Event::fake([LineAdded::class]);

        $first = $this->tab();
        $second = $this->tab();
        $first->count();
        $second->count();

        $first->add(new FakeProduct(id: 1));
        $second->add(new FakeProduct(id: 1));

        $this->assertSame(2, $this->tab()->totalQuantity());
        $this->assertSame(1, CartRecord::query()->count());
        Event::assertDispatchedTimes(LineAdded::class, 2);
    }

    public function test_toggle_decides_on_fresh_state(): void
    {
        Event::fake([LineAdded::class, LineRemoved::class]);

        $stale = $this->tab();
        $stale->count();

        $this->tab()->toggle(new FakeProduct(id: 1));

        $this->assertFalse($stale->toggle(new FakeProduct(id: 1)));
        $this->assertSame(0, $this->tab()->count());
        Event::assertDispatchedTimes(LineAdded::class, 1);
        Event::assertDispatchedTimes(LineRemoved::class, 1);
    }

    public function test_change_quantity_applies_delta_to_fresh_quantity(): void
    {
        $line = $this->tab()->add(new FakeProduct(id: 1), quantity: 2);

        $stale = $this->tab();
        $stale->count();

        $this->tab()->changeQuantity($line->id, 3);
        $stale->changeQuantity($line->id, 1);

        $this->assertSame(6, $this->tab()->totalQuantity());
    }

    public function test_noop_operation_does_not_bump_version(): void
    {
        $this->tab()->add(new FakeProduct(id: 1));

        $this->tab()->remove('missing');

        $this->assertSame(1, CartRecord::query()->value('version'));
    }

    private function tab(): ManagedList
    {
        return (new CartManager(
            storage: new DatabaseStorage(new FixedIdentity('guest-1')),
            config: $this->app['config'],
            events: $this->app['events'],
        ))->list('cart');
    }
}
