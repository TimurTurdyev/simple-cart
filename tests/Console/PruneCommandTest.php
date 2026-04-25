<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Console;

use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class PruneCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('simple_cart.storage', 'database');
    }

    public function test_idle_active_lists_expire(): void
    {
        $idle = $this->active('guest-1', idleDays: 40);
        $fresh = $this->active('guest-2', idleDays: 1);

        $this->artisan('simple-cart:prune')
            ->expectsOutput('Expired 1 idle list(s).')
            ->assertSuccessful();

        $idle->refresh();
        $this->assertSame(ListStatus::Expired, $idle->status);
        $this->assertSame((string) $idle->id, $idle->slot);
        $this->assertNotNull($idle->status_changed_at);
        $this->assertSame(ListStatus::Active, $fresh->refresh()->status);
    }

    public function test_expiry_can_be_disabled(): void
    {
        config()->set('simple_cart.prune.expire_after_days', null);
        $this->active('guest-1', idleDays: 400);

        $this->artisan('simple-cart:prune')->assertSuccessful();

        $this->assertSame(1, CartRecord::query()->active()->count());
    }

    public function test_closed_lists_are_deleted_by_status_retention(): void
    {
        $this->closed(ListStatus::Expired, days: 40);
        $this->closed(ListStatus::Expired, days: 10);
        $this->closed(ListStatus::Merged, days: 8);
        $this->closed(ListStatus::Ordered, days: 4000);

        $this->artisan('simple-cart:prune')
            ->expectsOutput('Deleted 1 expired list(s).')
            ->expectsOutput('Deleted 1 merged list(s).')
            ->assertSuccessful();

        $this->assertSame(1, CartRecord::query()->status(ListStatus::Expired)->count());
        $this->assertSame(0, CartRecord::query()->status(ListStatus::Merged)->count());
        $this->assertSame(1, CartRecord::query()->status(ListStatus::Ordered)->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->active('guest-1', idleDays: 40);
        $this->closed(ListStatus::Merged, days: 8);

        $this->artisan('simple-cart:prune', ['--dry-run' => true])
            ->expectsOutput('Would expire 1 idle list(s).')
            ->expectsOutput('Would delete 1 merged list(s).')
            ->assertSuccessful();

        $this->assertSame(1, CartRecord::query()->active()->count());
        $this->assertSame(1, CartRecord::query()->status(ListStatus::Merged)->count());
    }

    public function test_expiry_drops_cache_key_of_the_owner(): void
    {
        config()->set('simple_cart.cache.enabled', true);
        config()->set('simple_cart.cache.store', 'array');
        $cache = $this->app->make('cache')->store('array');
        $cache->put('simple_cart_guest-1', ['cart' => ['lines' => ['a']]]);
        $this->active('guest-1', idleDays: 40);

        $this->artisan('simple-cart:prune')->assertSuccessful();

        $this->assertFalse($cache->has('simple_cart_guest-1'));
    }

    public function test_unknown_status_is_reported(): void
    {
        config()->set('simple_cart.prune.delete_after_days', ['converted' => 1]);

        $this->artisan('simple-cart:prune')
            ->expectsOutput('Unknown closed status [converted] in simple_cart.prune.delete_after_days, skipped.')
            ->assertSuccessful();
    }

    public function test_non_database_storage_is_a_noop(): void
    {
        config()->set('simple_cart.storage', 'session');

        $this->artisan('simple-cart:prune')
            ->expectsOutput('Pruning works with the database storage only, nothing to do.')
            ->assertSuccessful();
    }

    private function active(string $owner, int $idleDays): CartRecord
    {
        return $this->record([
            'owner' => $owner,
            'updated_at' => now()->subDays($idleDays),
        ]);
    }

    private function closed(ListStatus $status, int $days): CartRecord
    {
        static $slot = 1000;

        return $this->record([
            'owner' => 'owner-'.$slot,
            'status' => $status,
            'slot' => (string) $slot++,
            'status_changed_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ]);
    }

    private function record(array $attributes): CartRecord
    {
        $record = new CartRecord(['list' => 'cart', 'payload' => ['lines' => []], ...$attributes]);
        $record->timestamps = false;
        $record->created_at = $record->updated_at;
        $record->save();

        return $record;
    }
}
