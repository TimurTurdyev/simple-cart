<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use Illuminate\Support\Manager;
use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Identity\AuthAwareIdentity;
use TimurTurdyev\Cart\Identity\IdentityManager;

final class StorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('cart.storage', 'session');
    }

    protected function createDriver($driver)
    {
        $storage = parent::createDriver($driver);

        if (! $this->config->get('cart.cache.enabled')) {
            return $storage;
        }

        $config = $this->config->get('cart.cache', []);

        return new CachedStorage(
            inner: $storage,
            identity: $this->ownerIdentity(),
            cache: $this->container->make('cache')->store($config['store'] ?? null),
            ttlMinutes: (int) ($config['ttl_minutes'] ?? 60 * 24 * 30),
            prefix: (string) ($config['prefix'] ?? 'cart_'),
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
