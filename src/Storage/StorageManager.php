<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Storage;

use Illuminate\Support\Manager;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Identity\AuthAwareIdentity;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;

final class StorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('simple_cart.storage', 'database');
    }

    protected function createDriver($driver)
    {
        $storage = parent::createDriver($driver);

        if (! $this->config->get('simple_cart.cache.enabled')) {
            return $storage;
        }

        $config = $this->config->get('simple_cart.cache', []);

        return new CachedStorage(
            inner: $storage,
            identity: $this->ownerIdentity(),
            cache: $this->container->make('cache')->store($config['store'] ?? null),
            ttlMinutes: (int) ($config['ttl_minutes'] ?? 60 * 24 * 30),
            prefix: (string) ($config['prefix'] ?? 'simple_cart_'),
        );
    }

    protected function createSessionDriver(): Storage
    {
        return new SessionStorage($this->container->make('session.store'));
    }

    protected function createDatabaseDriver(): Storage
    {
        return new DatabaseStorage(
            identity: $this->ownerIdentity(),
            retries: (int) $this->config->get('simple_cart.database.retries', 3),
        );
    }

    private function ownerIdentity(): CartIdentity
    {
        return new AuthAwareIdentity(
            authId: fn (): ?string => $this->container->make(IdentityManager::class)->authOwner(),
            guest: $this->container->make(IdentityManager::class)->driver(),
        );
    }
}
