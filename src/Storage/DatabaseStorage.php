<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Contracts\CartIdentity;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Contracts\SupportsOwnerMerge;
use TimurTurdyev\Cart\Contracts\SupportsOwnerScope;
use TimurTurdyev\Cart\Identity\FixedIdentity;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;

final readonly class DatabaseStorage implements Storage, SupportsOwnerMerge, SupportsOwnerScope
{
    public function __construct(
        private CartIdentity $identity,
    ) {
    }

    public function forOwner(string $owner): Storage
    {
        return new self(new FixedIdentity($owner));
    }

    public function read(string $list): array
    {
        $payload = CartRecord::query()
            ->where('owner', $this->identity->id())
            ->where('list', $list)
            ->value('payload');

        return is_string($payload) ? json_decode($payload, true) : ($payload ?? []);
    }

    public function write(string $list, array $payload): void
    {
        if ($payload === []) {
            $this->forget($list);

            return;
        }

        $this->identity->persist();

        CartRecord::query()->updateOrCreate(
            ['owner' => $this->identity->id(), 'list' => $list],
            ['payload' => $payload],
        );
    }

    public function forget(string $list): void
    {
        CartRecord::query()
            ->where('owner', $this->identity->id())
            ->where('list', $list)
            ->delete();
    }

    /**
     * @param array<string, ListPolicy> $policies
     */
    public function mergeOwners(string $from, string $to, MergeStrategy $strategy, array $policies): void
    {
        $guestRecords = CartRecord::query()->where('owner', $from)->get();

        foreach ($guestRecords as $record) {
            $policy = $policies[$record->list] ?? null;

            if ($policy === null) {
                continue;
            }

            $userPayload = CartRecord::query()
                ->where('owner', $to)
                ->where('list', $record->list)
                ->value('payload') ?? [];

            $merged = Cart::fromArray($policy, $userPayload)
                ->merge(Cart::fromArray($policy, $record->payload ?? []), $strategy);

            CartRecord::query()->updateOrCreate(
                ['owner' => $to, 'list' => $record->list],
                ['payload' => $merged->toArray()],
            );
        }

        CartRecord::query()->where('owner', $from)->delete();
    }
}
