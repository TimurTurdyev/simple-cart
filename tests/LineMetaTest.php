<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\LineAdded;
use TimurTurdyev\SimpleCart\Events\LineMetaUpdated;
use TimurTurdyev\SimpleCart\Events\LineRemoved;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class LineMetaTest extends TestCase
{
    public function test_meta_is_updated_in_place_with_one_write(): void
    {
        $first = $this->tab()->add(new FakeProduct(id: 1, price: 1000), quantity: 2, meta: ['sku' => 'A']);
        $second = $this->tab()->add(new FakeProduct(id: 2));
        $this->tab()->repriceLine($first->id, Price::fromMinor(1200));
        $version = CartRecord::query()->value('version');
        Event::fake([LineMetaUpdated::class, LineAdded::class, LineRemoved::class]);

        $line = $this->tab()->setMeta($first->id, ['gone' => true]);

        $stored = $this->tab()->get($first->id);
        $this->assertEquals($line, $stored);
        $this->assertSame(['sku' => 'A', 'gone' => true], $stored->meta);
        $this->assertSame([2, 1200, 1000], [$stored->quantity, $stored->price->minor(), $stored->previousPrice->minor()]);
        $this->assertSame([$first->id, $second->id], $this->tab()->items()->keys()->all());
        $this->assertSame($version + 1, CartRecord::query()->value('version'));
        Event::assertDispatched(LineMetaUpdated::class, fn (LineMetaUpdated $e): bool => $e->line->id === $first->id && $e->changed === ['gone']);
        Event::assertNotDispatched(LineAdded::class);
        Event::assertNotDispatched(LineRemoved::class);

        $this->tab()->setMeta($first->id, ['gone' => true, 'sku' => 'A']);

        $this->assertSame($version + 1, CartRecord::query()->value('version'));
        Event::assertDispatchedTimes(LineMetaUpdated::class, 1);
    }

    public function test_meta_is_merged_into_fresh_line(): void
    {
        $line = $this->tab()->add(new FakeProduct(id: 1), meta: ['sku' => 'A']);
        $stale = $this->tab();
        $stale->count();

        $this->tab()->setQuantity($line->id, 3);
        $this->tab()->setMeta($line->id, ['note' => 'gift']);
        $stale->setMeta($line->id, ['gone' => true]);

        $stored = $this->tab()->get($line->id);
        $this->assertSame(3, $stored->quantity);
        $this->assertSame(['sku' => 'A', 'note' => 'gift', 'gone' => true], $stored->meta);
    }

    public function test_meta_does_not_bring_back_removed_line(): void
    {
        $line = $this->tab()->add(new FakeProduct(id: 1));
        $this->tab()->add(new FakeProduct(id: 2));
        $stale = $this->tab();
        $stale->count();

        $this->tab()->remove($line->id);

        try {
            $stale->setMeta($line->id, ['gone' => true]);
            $this->fail('A removed line must not be updated.');
        } catch (UnknownLineException) {
        }

        $this->assertFalse($this->tab()->has($line->id));
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
