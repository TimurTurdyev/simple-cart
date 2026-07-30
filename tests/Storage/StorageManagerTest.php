<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Storage;

use InvalidArgumentException;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Storage\SessionStorage;
use TimurTurdyev\Cart\Storage\StorageManager;
use TimurTurdyev\Cart\Tests\TestCase;

final class StorageManagerTest extends TestCase
{
    public function test_default_driver_is_session(): void
    {
        $manager = $this->app->make(StorageManager::class);

        $this->assertInstanceOf(SessionStorage::class, $manager->driver());
    }

    public function test_storage_contract_resolves_default_driver(): void
    {
        $this->assertInstanceOf(SessionStorage::class, $this->app->make(Storage::class));
    }

    public function test_custom_driver_via_extend(): void
    {
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

        config()->set('cart.storage', 'custom');

        $manager = $this->app->make(StorageManager::class);
        $manager->extend('custom', fn (): Storage => $custom);

        $this->assertSame($custom, $manager->driver());
        $this->assertSame($custom, $this->app->make(Storage::class));
    }

    public function test_unknown_driver_fails(): void
    {
        config()->set('cart.storage', 'missing');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(StorageManager::class)->driver();
    }
}
