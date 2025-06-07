<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use Illuminate\Contracts\Cache\Repository;
use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Contracts\SupportsOwnerMerge;
use TimurTurdyev\Cart\Contracts\SupportsOwnerScope;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;

final readonly class CachedStorage implements Storage, SupportsOwnerMerge, SupportsOwnerScope
{
    public function __construct(
        private Storage $inner,
        private CartIdentity $identity,
        private Repository $cache,
        private int $ttlMinutes,
        private string $prefix,
    ) {
    }

    public function read(string $list): array
    {
        $lists = $this->cachedLists();

        if (array_key_exists($list, $lists)) {
            return $lists[$list];
        }

        $payload = $this->inner->read($list);

        $this->store([...$lists, $list => $payload]);

        return $payload;
    }

    public function write(string $list, array $payload): void
    {
        $this->inner->write($list, $payload);

        $this->store([...$this->cachedLists(), $list => $payload]);
    }

    public function forget(string $list): void
    {
        $this->inner->forget($list);

        $this->store([...$this->cachedLists(), $list => []]);
    }

    /**
     * Scoped writes go straight to the inner storage: bulk imports must not
     * prime per-visitor cache keys.
     */
    public function forOwner(string $owner): Storage
    {
        return $this->inner instanceof SupportsOwnerScope
            ? $this->inner->forOwner($owner)
            : $this->inner;
    }

    /**
     * @param array<string, ListPolicy> $policies
     */
    public function mergeOwners(string $from, string $to, MergeStrategy $strategy, array $policies): void
    {
        if (! $this->inner instanceof SupportsOwnerMerge) {
            return;
        }

        $this->inner->mergeOwners($from, $to, $strategy, $policies);

        $this->cache->forget($this->prefix.$from);
        $this->cache->forget($this->prefix.$to);
    }

    /**
     * A cache-key entry holds every known list of the cart; a present key with
     * an empty payload means "loaded and empty", a missing key means "not
     * loaded yet".
     *
     * @return array<string, array>
     */
    private function cachedLists(): array
    {
        $lists = $this->cache->get($this->key());

        return is_array($lists) ? $lists : [];
    }

    /**
     * @param array<string, array> $lists
     */
    private function store(array $lists): void
    {
        $this->cache->put($this->key(), $lists, $this->ttlMinutes * 60);
    }

    private function key(): string
    {
        return $this->prefix.$this->identity->id();
    }
}
