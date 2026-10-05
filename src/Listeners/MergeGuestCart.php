<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Config\Repository;
use TimurTurdyev\SimpleCart\Contracts\SupportsOwnerMerge;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\MergeStrategy;
use TimurTurdyev\SimpleCart\Storage\StorageManager;

final readonly class MergeGuestCart
{
    public function __construct(
        private StorageManager $storage,
        private IdentityManager $identity,
        private Repository $config,
    ) {
    }

    public function handle(Login $event): void
    {
        $guard = $this->identity->authGuard();

        if ($guard !== null && $event->guard !== $guard) {
            return;
        }

        $driver = $this->storage->driver();

        if (! $driver instanceof SupportsOwnerMerge) {
            return;
        }

        $policies = array_map(
            ListPolicy::fromConfig(...),
            $this->config->get('simple_cart.lists', []),
        );

        $driver->mergeOwners(
            from: $this->identity->driver()->id(),
            to: $this->identity->ownerFor($event->user),
            strategy: MergeStrategy::from($this->config->get('simple_cart.merge.strategy', 'sum')),
            policies: $policies,
        );
    }
}
