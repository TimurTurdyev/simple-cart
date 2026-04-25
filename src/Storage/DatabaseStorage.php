<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Storage;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use TimurTurdyev\SimpleCart\Cart;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Contracts\SupportsAtomicUpdate;
use TimurTurdyev\SimpleCart\Contracts\SupportsLifecycle;
use TimurTurdyev\SimpleCart\Contracts\SupportsOwnerMerge;
use TimurTurdyev\SimpleCart\Contracts\SupportsOwnerScope;
use TimurTurdyev\SimpleCart\Exceptions\ConcurrentModificationException;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\MergeStrategy;

final readonly class DatabaseStorage implements Storage, SupportsAtomicUpdate, SupportsLifecycle, SupportsOwnerMerge, SupportsOwnerScope
{
    public function __construct(
        private CartIdentity $identity,
        private int $retries = 3,
    ) {
    }

    public function forOwner(string $owner): Storage
    {
        return new self(new FixedIdentity($owner), $this->retries);
    }

    public function read(string $list): array
    {
        return $this->decode($this->active($list)->value('payload'));
    }

    public function write(string $list, array $payload): void
    {
        $this->update($list, fn (): array => $payload);
    }

    public function update(string $list, Closure $mutator): array
    {
        $attempts = max(1, $this->retries);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $record = $this->active($list)->first(['id', 'payload', 'version']);
            $current = $record === null ? [] : $this->decode($record->payload);
            $payload = $mutator($current);

            if ($payload === $current || $this->commit($list, $record, $payload)) {
                return $payload;
            }
        }

        throw ConcurrentModificationException::forList($list, $attempts);
    }

    public function forget(string $list): void
    {
        $this->active($list)->delete();
    }

    public function close(string $list, ListStatus $status, ?string $reference = null): array
    {
        return CartRecord::query()->getConnection()->transaction(function () use ($list, $status, $reference): array {
            $record = $this->active($list)->lockForUpdate()->first();

            if ($record === null) {
                return [];
            }

            $this->closeRecord($record, $status, $reference);

            return $this->decode($record->payload);
        });
    }

    /**
     * Guest lists are merged into the user lists and kept as closed records
     * with the "merged" status pointing at the user.
     *
     * @param array<string, ListPolicy> $policies
     */
    public function mergeOwners(string $from, string $to, MergeStrategy $strategy, array $policies): void
    {
        if ($from === $to) {
            return;
        }

        CartRecord::query()->getConnection()->transaction(function () use ($from, $to, $strategy, $policies): void {
            $guestRecords = CartRecord::query()->active()->where('owner', $from)->lockForUpdate()->get();

            foreach ($guestRecords as $record) {
                $policy = $policies[$record->list] ?? null;

                if ($policy === null) {
                    continue;
                }

                $userRecord = CartRecord::query()
                    ->active()
                    ->where('owner', $to)
                    ->where('list', $record->list)
                    ->lockForUpdate()
                    ->first();

                $merged = Cart::fromArray($policy, $userRecord?->payload ?? [])
                    ->merge(Cart::fromArray($policy, $record->payload ?? []), $strategy);

                $this->saveMerged($to, $record->list, $userRecord, $merged->toArray());
                $this->closeRecord($record, ListStatus::Merged, $to);
            }
        });
    }

    private function saveMerged(string $owner, string $list, ?CartRecord $record, array $payload): void
    {
        if ($record === null) {
            CartRecord::query()->create([
                'owner' => $owner,
                'list' => $list,
                'payload' => $payload,
                'version' => 1,
                'slot' => CartRecord::ACTIVE_SLOT,
            ]);

            return;
        }

        $record->forceFill(['payload' => $payload, 'version' => $record->version + 1])->save();
    }

    private function closeRecord(CartRecord $record, ListStatus $status, ?string $reference): void
    {
        $record->forceFill([
            'status' => $status,
            'status_changed_at' => $record->freshTimestamp(),
            'reference' => $reference,
            'slot' => (string) $record->getKey(),
            'version' => $record->version + 1,
        ])->save();
    }

    private function commit(string $list, ?CartRecord $record, array $payload): bool
    {
        if ($record === null) {
            return $payload === [] || $this->insert($list, $payload);
        }

        $unchanged = CartRecord::query()
            ->whereKey($record->getKey())
            ->where('version', $record->version);

        if ($payload === []) {
            return $unchanged->delete() === 1;
        }

        $this->identity->persist();

        return $unchanged->update([
            'payload' => json_encode($payload),
            'version' => $record->version + 1,
        ]) === 1;
    }

    private function insert(string $list, array $payload): bool
    {
        $this->identity->persist();

        try {
            CartRecord::query()->getConnection()->transaction(fn () => CartRecord::query()->create([
                'owner' => $this->identity->id(),
                'list' => $list,
                'payload' => $payload,
                'version' => 1,
                'slot' => CartRecord::ACTIVE_SLOT,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * @return Builder<CartRecord>
     */
    private function active(string $list): Builder
    {
        return CartRecord::query()
            ->active()
            ->where('owner', $this->identity->id())
            ->where('list', $list);
    }

    private function decode(mixed $payload): array
    {
        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;

        return is_array($decoded) ? $decoded : [];
    }
}
