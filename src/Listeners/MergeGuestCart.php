<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Session\Session;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;
use TimurTurdyev\Cart\Storage\DatabaseStorage;
use TimurTurdyev\Cart\Storage\StorageManager;

final readonly class MergeGuestCart
{
    public function __construct(
        private StorageManager $storage,
        private Session $session,
        private Repository $config,
    ) {
    }

    public function handle(Login $event): void
    {
        $driver = $this->storage->driver();

        if (! $driver instanceof DatabaseStorage) {
            return;
        }

        $policies = array_map(
            ListPolicy::fromConfig(...),
            $this->config->get('cart.lists', []),
        );

        $driver->mergeOwners(
            from: $this->session->getId(),
            to: (string) $event->user->getAuthIdentifier(),
            strategy: MergeStrategy::from($this->config->get('cart.merge.strategy', 'sum')),
            policies: $policies,
        );
    }
}
