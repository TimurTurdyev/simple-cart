<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Storage;

use TimurTurdyev\SimpleCart\Cart;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Exceptions\ConcurrentModificationException;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\MergeStrategy;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class DatabaseStorageTest extends TestCase
{
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
        $this->assertSame(0, CartRecord::query()->active()->where('owner', 'guest-1')->count());
        $this->assertSame(ListStatus::Merged, CartRecord::query()->where('owner', 'guest-1')->value('status'));
    }

    public function test_merge_owners_keeps_lists_without_policy(): void
    {
        $policy = new ListPolicy();
        $line = Line::of(1, 'Item', Price::fromMinor(100));

        $this->storage('guest-1')->write('cart', Cart::make($policy)->add($line)->toArray());
        $this->storage('guest-1')->write('legacy', Cart::make($policy)->add($line)->toArray());

        $this->storage('42')->mergeOwners('guest-1', '42', MergeStrategy::Sum, ['cart' => $policy]);

        $this->assertSame(1, CartRecord::query()->where('owner', '42')->count());
        $this->assertSame(1, CartRecord::query()->where('owner', 'guest-1')->where('list', 'legacy')->count());
    }

    public function test_update_reapplies_mutator_to_fresh_payload_on_version_conflict(): void
    {
        $storage = $this->storage('guest-1');
        $storage->write('cart', ['lines' => ['a']]);

        $calls = 0;
        $result = $storage->update('cart', function (array $payload) use (&$calls): array {
            if (++$calls === 1) {
                $this->storage('guest-1')->write('cart', ['lines' => ['a', 'b']]);
            }

            return ['lines' => [...$payload['lines'], 'c']];
        });

        $this->assertSame(2, $calls);
        $this->assertSame(['lines' => ['a', 'b', 'c']], $result);
        $this->assertSame(['lines' => ['a', 'b', 'c']], $storage->read('cart'));
        $this->assertSame(3, CartRecord::query()->value('version'));
    }

    public function test_update_retries_insert_race(): void
    {
        $storage = $this->storage('guest-1');

        $calls = 0;
        $result = $storage->update('cart', function (array $payload) use (&$calls): array {
            if (++$calls === 1) {
                $this->storage('guest-1')->write('cart', ['lines' => ['a']]);
            }

            return ['lines' => [...($payload['lines'] ?? []), 'b']];
        });

        $this->assertSame(['lines' => ['a', 'b']], $result);
        $this->assertSame(1, CartRecord::query()->count());
    }

    public function test_update_gives_up_after_configured_attempts(): void
    {
        $storage = $this->storage('guest-1', retries: 2);
        $storage->write('cart', ['lines' => ['a']]);

        $calls = 0;

        try {
            $storage->update('cart', function (array $payload) use (&$calls): array {
                $calls++;
                $this->storage('guest-1')->write('cart', ['lines' => ['x'.$calls]]);

                return ['lines' => ['mine']];
            });
            $this->fail('Expected ConcurrentModificationException.');
        } catch (ConcurrentModificationException) {
            $this->assertSame(2, $calls);
        }

        $this->assertSame(['lines' => ['x2']], $storage->read('cart'));
    }

    public function test_update_with_empty_result_deletes_record(): void
    {
        $storage = $this->storage('guest-1');
        $storage->write('cart', ['lines' => ['a']]);

        $this->assertSame([], $storage->update('cart', fn (): array => []));
        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_write_bumps_version(): void
    {
        $storage = $this->storage('guest-1');

        $storage->write('cart', ['lines' => ['a']]);
        $this->assertSame(1, CartRecord::query()->value('version'));

        $storage->write('cart', ['lines' => ['b']]);
        $this->assertSame(2, CartRecord::query()->value('version'));
    }

    public function test_closed_records_are_invisible_to_active_reads(): void
    {
        CartRecord::query()->create([
            'owner' => 'guest-1', 'list' => 'cart', 'payload' => ['lines' => ['old']], 'slot' => '7', 'status' => 'ordered',
        ]);

        $storage = $this->storage('guest-1');

        $this->assertSame([], $storage->read('cart'));

        $storage->write('cart', ['lines' => ['new']]);

        $this->assertSame(['lines' => ['new']], $storage->read('cart'));
        $this->assertSame(2, CartRecord::query()->count());
    }

    private function storage(string $owner, int $retries = 3): DatabaseStorage
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
        }, $retries);
    }
}
