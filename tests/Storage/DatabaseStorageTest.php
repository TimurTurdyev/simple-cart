<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Storage;

use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Line;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;
use TimurTurdyev\Cart\Storage\CartRecord;
use TimurTurdyev\Cart\Storage\DatabaseStorage;
use TimurTurdyev\Cart\Support\Price;
use TimurTurdyev\Cart\Tests\TestCase;

final class DatabaseStorageTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_roundtrip(): void
    {
        $storage = $this->storage('guest-1');
        $payload = ['lines' => [['id' => 'abc']]];

        $storage->write('cart', $payload);

        $this->assertSame($payload, $storage->read('cart'));
    }

    public function test_owners_and_lists_are_isolated(): void
    {
        $this->storage('guest-1')->write('cart', ['lines' => ['a']]);
        $this->storage('guest-1')->write('wishlist', ['lines' => ['b']]);
        $this->storage('guest-2')->write('cart', ['lines' => ['c']]);

        $this->assertSame(['lines' => ['a']], $this->storage('guest-1')->read('cart'));
        $this->assertSame(['lines' => ['b']], $this->storage('guest-1')->read('wishlist'));
        $this->assertSame(['lines' => ['c']], $this->storage('guest-2')->read('cart'));
    }

    public function test_empty_write_deletes_record(): void
    {
        $storage = $this->storage('guest-1');

        $storage->write('cart', ['lines' => ['a']]);
        $storage->write('cart', []);

        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_merge_owners(): void
    {
        $policy = new ListPolicy();
        $policies = ['cart' => $policy];
        $line = Line::of(1, 'Item', Price::fromMinor(100), 2);

        $this->storage('guest-1')->write('cart', Cart::make($policy)->add($line)->toArray());
        $this->storage('42')->write('cart', Cart::make($policy)->add($line)->toArray());

        $this->storage('42')->mergeOwners('guest-1', '42', MergeStrategy::Sum, $policies);

        $restored = Cart::fromArray($policy, $this->storage('42')->read('cart'));

        $this->assertSame(4, $restored->totalQuantity());
        $this->assertSame(0, CartRecord::query()->where('owner', 'guest-1')->count());
    }

    private function storage(string $owner): DatabaseStorage
    {
        return new DatabaseStorage(new class($owner) implements CartIdentity
        {
            public function __construct(private readonly string $owner)
            {
            }

            public function id(): string
            {
                return $this->owner;
            }

            public function persist(): void
            {
            }
        });
    }
}
