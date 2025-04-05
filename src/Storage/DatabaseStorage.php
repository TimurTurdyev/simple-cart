<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use Closure;
use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;

final readonly class DatabaseStorage implements Storage
{
    public function __construct(
        private Closure $owner,
    ) {
    }

    public function read(string $list): array
    {
        $payload = CartRecord::query()
            ->where('owner', $this->ownerId())
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

        CartRecord::query()->updateOrCreate(
            ['owner' => $this->ownerId(), 'list' => $list],
            ['payload' => $payload],
        );
    }

    public function forget(string $list): void
    {
        CartRecord::query()
            ->where('owner', $this->ownerId())
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

    private function ownerId(): string
    {
        return ($this->owner)();
    }
}
