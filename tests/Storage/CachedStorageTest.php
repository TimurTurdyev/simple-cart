<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Storage;

use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Identity\AuthAwareIdentity;
use TimurTurdyev\SimpleCart\Exceptions\ConcurrentModificationException;
use TimurTurdyev\SimpleCart\Storage\CachedStorage;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Storage\StorageManager;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class CachedStorageTest extends TestCase
{
    public function test_read_hits_inner_only_on_cache_miss(): void
    {
        $inner = new CountingStorage(['cart' => ['lines' => ['a']]]);
        $cached = $this->cached($inner);

        $this->assertSame(['lines' => ['a']], $cached->read('cart'));
        $this->assertSame(['lines' => ['a']], $cached->read('cart'));

        $this->assertSame(1, $inner->readCalls);
    }

    public function test_empty_inner_payload_is_cached_too(): void
    {
        $inner = new CountingStorage();
        $cached = $this->cached($inner);

        $this->assertSame([], $cached->read('cart'));
        $this->assertSame([], $cached->read('cart'));

        $this->assertSame(1, $inner->readCalls);
    }

    public function test_write_goes_to_inner_first_and_updates_cache(): void
    {
        $inner = new CountingStorage();
        $cached = $this->cached($inner);

        $cached->write('cart', ['lines' => ['a']]);

        $this->assertSame(1, $inner->writeCalls);
        $this->assertSame(['lines' => ['a']], $inner->read('cart'));

        $this->assertSame(['lines' => ['a']], $cached->read('cart'));
        $this->assertSame(1, $inner->readCalls);
    }

    public function test_forget_updates_cache(): void
    {
        $inner = new CountingStorage();
        $cached = $this->cached($inner);

        $cached->write('cart', ['lines' => ['a']]);
        $cached->forget('cart');

        $this->assertSame([], $cached->read('cart'));
        $this->assertSame(0, $inner->readCalls);
    }

    public function test_all_lists_share_one_cache_key(): void
    {
        $cached = $this->cached(new CountingStorage(), 'owner-1');

        $cached->write('cart', ['lines' => ['a']]);
        $cached->write('wishlist', ['lines' => ['b']]);

        $entry = $this->app->make('cache')->store('array')->get('simple_cart_owner-1');

        $this->assertSame(['lines' => ['a']], $entry['cart']);
        $this->assertSame(['lines' => ['b']], $entry['wishlist']);
    }

    public function test_cache_key_follows_resolved_owner_on_login(): void
    {
        $authId = null;
        $identity = new AuthAwareIdentity(
            authId: function () use (&$authId): int|string|null {
                return $authId;
            },
            guest: new FixedOwner('guest-1'),
        );

        $inner = new OwnerAwareStorage($identity);
        $cached = $this->cached($inner, $identity);

        $cached->write('cart', ['lines' => ['guest']]);

        $authId = 42;

        $this->assertSame([], $cached->read('cart'));
        $this->assertSame(1, $inner->readCalls);

        $store = $this->app->make('cache')->store('array');

        $this->assertSame(['lines' => ['guest']], $store->get('simple_cart_guest-1')['cart']);
        $this->assertSame([], $store->get('simple_cart_42')['cart']);
    }

    public function test_manager_wraps_custom_extend_driver(): void
    {
        config()->set('simple_cart.identity.driver', 'session');
        config()->set('simple_cart.storage', 'custom');
        config()->set('simple_cart.cache', [
            'enabled' => true,
            'store' => 'array',
            'ttl_minutes' => 60,
            'prefix' => 'x_',
        ]);

        $inner = new CountingStorage();
        $manager = $this->app->make(StorageManager::class);
        $manager->extend('custom', fn (): Storage => $inner);

        $driver = $manager->driver();

        $this->assertInstanceOf(CachedStorage::class, $driver);

        $driver->write('cart', ['lines' => ['a']]);

        $this->assertSame(1, $inner->writeCalls);

        $owner = $this->app->make('session.store')->getId();

        $this->assertSame(
            ['lines' => ['a']],
            $this->app->make('cache')->store('array')->get('x_'.$owner)['cart'],
        );
    }

    public function test_warm_cache_read_makes_no_database_queries(): void
    {
        config()->set('simple_cart.storage', 'database');
        config()->set('simple_cart.cache.enabled', true);

        $this->writer()->list('cart')->add(new FakeProduct(price: 500));

        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();

        $this->assertSame(1, $this->reader()->list('cart')->count());
        $this->assertCount(1, $connection->getQueryLog(), 'A write drops the key, the first read primes it.');

        $connection->flushQueryLog();

        $this->assertSame(1, $this->reader()->list('cart')->count());
        $this->assertCount(0, $connection->getQueryLog());
    }

    public function test_uncached_database_read_makes_single_query_per_list(): void
    {
        config()->set('simple_cart.storage', 'database');
        config()->set('simple_cart.cache.enabled', false);

        $this->writer()->list('cart')->add(new FakeProduct(price: 500));

        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();

        $this->assertSame(1, $this->reader()->list('cart')->count());
        $this->assertCount(1, $connection->getQueryLog());
    }

    public function test_atomic_update_drops_cache_key_and_next_read_goes_to_inner(): void
    {
        $cached = $this->cached(new DatabaseStorage(new FixedOwner('owner-1')));

        $this->assertSame([], $cached->read('cart'));

        $result = $cached->update('cart', fn (array $payload): array => ['lines' => ['a']]);

        $this->assertSame(['lines' => ['a']], $result);
        $this->assertFalse($this->app->make('cache')->store('array')->has('simple_cart_owner-1'));
        $this->assertSame(['lines' => ['a']], $cached->read('cart'));
    }

    public function test_failed_atomic_update_drops_cache_key(): void
    {
        $inner = new DatabaseStorage(new FixedOwner('owner-1'), retries: 1);
        $cached = $this->cached($inner);
        $inner->write('cart', ['lines' => ['a']]);
        $cached->read('cart');

        try {
            $cached->update('cart', function (): array {
                (new DatabaseStorage(new FixedOwner('owner-1')))->write('cart', ['lines' => ['other']]);

                return ['lines' => ['mine']];
            });
            $this->fail('Expected ConcurrentModificationException.');
        } catch (ConcurrentModificationException) {
        }

        $this->assertSame(['lines' => ['other']], $cached->read('cart'));
    }

    public function test_update_over_plain_inner_falls_back_to_write(): void
    {
        $inner = new CountingStorage(['cart' => ['lines' => ['a']]]);
        $cached = $this->cached($inner);

        $result = $cached->update('cart', fn (array $payload): array => ['lines' => [...$payload['lines'], 'b']]);

        $this->assertSame(['lines' => ['a', 'b']], $result);
        $this->assertSame(['lines' => ['a', 'b']], $inner->read('cart'));
    }

    private function cached(Storage $inner, CartIdentity|string $identity = 'owner-1'): CachedStorage
    {
        return new CachedStorage(
            inner: $inner,
            identity: is_string($identity) ? new FixedOwner($identity) : $identity,
            cache: $this->app->make('cache')->store('array'),
            ttlMinutes: 60,
            prefix: 'simple_cart_',
        );
    }

    private function writer(): CartManager
    {
        return $this->manager();
    }

    private function reader(): CartManager
    {
        return $this->manager();
    }

    private function manager(): CartManager
    {
        return new CartManager(
            storage: $this->app->make(StorageManager::class)->driver(),
            config: $this->app->make('config'),
            events: null,
        );
    }
}

final class FixedOwner implements CartIdentity
{
    public function __construct(private readonly string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function persist(): void
    {
    }
}

final class OwnerAwareStorage implements Storage
{
    public int $readCalls = 0;

    private array $records = [];

    public function __construct(private readonly CartIdentity $identity)
    {
    }

    public function read(string $list): array
    {
        $this->readCalls++;

        return $this->records[$this->key($list)] ?? [];
    }

    public function write(string $list, array $payload): void
    {
        $this->records[$this->key($list)] = $payload;
    }

    public function forget(string $list): void
    {
        unset($this->records[$this->key($list)]);
    }

    private function key(string $list): string
    {
        return $this->identity->id().':'.$list;
    }
}

final class CountingStorage implements Storage
{
    public int $readCalls = 0;

    public int $writeCalls = 0;

    public function __construct(private array $records = [])
    {
    }

    public function read(string $list): array
    {
        $this->readCalls++;

        return $this->records[$list] ?? [];
    }

    public function write(string $list, array $payload): void
    {
        $this->writeCalls++;

        $this->records[$list] = $payload;
    }

    public function forget(string $list): void
    {
        unset($this->records[$list]);
    }
}
