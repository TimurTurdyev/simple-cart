<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TimurTurdyev\Cart\Storage\CartRecord;
use TimurTurdyev\Cart\Tests\TestCase;

final class ImportLegacyCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Schema::create('legacy_carts', function ($table): void {
            $table->string('identifier');
            $table->text('cart_data');
        });
    }

    public function test_imports_legacy_rows(): void
    {
        DB::table('legacy_carts')->insert([
            'identifier' => 'user-7',
            'cart_data' => json_encode([
                'items' => [['id' => 1, 'name' => 'Item', 'price' => 19.99, 'quantity' => 2]],
                'conditions' => [['name' => 'sale', 'value' => '-10%']],
            ]),
        ]);

        $this->artisan('cart:import-legacy', ['table' => 'legacy_carts'])
            ->expectsOutputToContain('Imported: 1, skipped: 0.')
            ->assertSuccessful();

        $payload = CartRecord::query()->where('owner', 'user-7')->where('list', 'cart')->first()?->payload;

        $this->assertSame(1999, $payload['lines'][0]['price']);
        $this->assertCount(1, $payload['adjusters']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        DB::table('legacy_carts')->insert([
            'identifier' => 'user-7',
            'cart_data' => json_encode(['items' => []]),
        ]);

        $this->artisan('cart:import-legacy', ['table' => 'legacy_carts', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_broken_rows_are_skipped(): void
    {
        DB::table('legacy_carts')->insert([
            ['identifier' => 'bad', 'cart_data' => 'O:8:"stdClass":0:{}'],
            ['identifier' => 'good', 'cart_data' => json_encode(['items' => [['id' => 1, 'name' => 'A', 'price' => 1.0, 'quantity' => 1]]])],
        ]);

        $this->artisan('cart:import-legacy', ['table' => 'legacy_carts'])
            ->expectsOutputToContain('Imported: 1, skipped: 1.')
            ->assertSuccessful();

        $this->assertSame(1, CartRecord::query()->count());
    }
}
