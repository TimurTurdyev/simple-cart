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
        return $this->config->get('simple_cart.storage', 'session');
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
        return new DatabaseStorage($this->ownerIdentity());
    }

    private function ownerIdentity(): CartIdentity
    {
        return new AuthAwareIdentity(
            authId: fn (): int|string|null => $this->container->make('auth')->guard()->id(),
            guest: $this->container->make(IdentityManager::class)->driver(),
        );
    }
}
