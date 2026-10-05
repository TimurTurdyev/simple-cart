<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;

final class PruneCommand extends Command
{
    protected $signature = 'simple-cart:prune {--dry-run : Count the records without changing them}';

    protected $description = 'Expire idle lists and delete closed lists past their retention period';

    public function handle(Repository $config, CacheFactory $cache): int
    {
        if ($config->get('simple_cart.storage') !== 'database') {
            $this->warn('Pruning works with the database storage only, nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prune = $config->get('simple_cart.prune', []);

        $this->expire($prune['expire_after_days'] ?? null, $dryRun, $config, $cache);

        foreach ($prune['delete_after_days'] ?? [] as $status => $days) {
            $this->delete((string) $status, $days, $dryRun);
        }

        return self::SUCCESS;
    }

    private function expire(?int $days, bool $dryRun, Repository $config, CacheFactory $cache): void
    {
        if ($days === null) {
            return;
        }

        $idle = CartRecord::query()->active()->idleSince(now()->subDays($days));

        if ($dryRun) {
            $this->info("Would expire {$idle->count()} idle list(s).");

            return;
        }

        $expired = 0;
        $store = $config->get('simple_cart.cache.enabled')
            ? $cache->store($config->get('simple_cart.cache.store'))
            : null;
        $prefix = (string) $config->get('simple_cart.cache.prefix', 'simple_cart_');

        $idle->chunkById(200, function (Collection $records) use (&$expired, $store, $prefix): void {
            foreach ($records as $record) {
                if (! $this->expireRecord($record)) {
                    continue;
                }

                $expired++;
                $store?->forget($prefix.$record->owner);
            }
        });

        $this->info("Expired {$expired} idle list(s).");
    }

    /**
     * Skips the record when its owner wrote to it since it was selected.
     */
    private function expireRecord(CartRecord $record): bool
    {
        return CartRecord::query()
            ->whereKey($record->getKey())
            ->where('version', $record->version)
            ->update([
                'status' => ListStatus::Expired->value,
                'status_changed_at' => now(),
                'slot' => (string) $record->getKey(),
                'version' => $record->version + 1,
            ]) === 1;
    }

    private function delete(string $status, ?int $days, bool $dryRun): void
    {
        $listStatus = ListStatus::tryFrom($status);

        if ($listStatus === null || $listStatus === ListStatus::Active) {
            $this->warn("Unknown closed status [{$status}] in simple_cart.prune.delete_after_days, skipped.");

            return;
        }

        if ($days === null) {
            return;
        }

        $closed = CartRecord::query()
            ->status($listStatus)
            ->where('slot', '!=', CartRecord::ACTIVE_SLOT)
            ->where('status_changed_at', '<', now()->subDays($days));

        $count = $dryRun ? $closed->count() : $closed->delete();
        $verb = $dryRun ? 'Would delete' : 'Deleted';

        $this->info("{$verb} {$count} {$status} list(s).");
    }
}
