<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Storage;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class MigrationTest extends TestCase
{
    private const string CREATE = __DIR__.'/../../database/migrations/2024_01_01_000001_create_simple_cart_lists_table.php';

    private const string UPGRADE = __DIR__.'/../../database/migrations/2026_10_05_000001_upgrade_simple_cart_lists_to_v3.php';

    protected function defineDatabaseMigrations(): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertFalse(Schema::hasTable('simple_cart_lists'), 'Migration tests start from an empty database.');
    }

    public function test_fresh_install_has_v3_schema(): void
    {
        $this->migration(self::CREATE)->up();
        $this->migration(self::UPGRADE)->up();

        foreach (['version', 'status', 'status_changed_at', 'reference', 'slot'] as $column) {
            $this->assertTrue(Schema::hasColumn('simple_cart_lists', $column), $column);
        }

        $this->assertTrue(Schema::hasIndex('simple_cart_lists', ['owner', 'list', 'slot'], 'unique'));
        $this->assertFalse(Schema::hasIndex('simple_cart_lists', ['owner', 'list'], 'unique'));
    }

    public function test_upgrade_keeps_v2_records_active(): void
    {
        $this->migration(self::CREATE)->up();

        $payload = ['lines' => [[
            'id' => 'abc', 'purchasable_id' => 7, 'name' => 'Box', 'price' => 1500, 'quantity' => 2,
        ]], 'adjusters' => []];

        DB::table('simple_cart_lists')->insert([
            'owner' => 'guest-1',
            'list' => 'cart',
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration(self::UPGRADE)->up();

        $record = CartRecord::query()->sole();

        $this->assertSame(ListStatus::Active, $record->status);
        $this->assertSame('', $record->slot);
        $this->assertSame(0, $record->version);
        $this->assertSame($payload, $record->payload);
        $this->assertSame(1, $record->cart()->count());
    }

    public function test_create_and_upgrade_are_idempotent(): void
    {
        $this->migration(self::CREATE)->up();
        $this->migration(self::UPGRADE)->up();
        $this->migration(self::CREATE)->up();
        $this->migration(self::UPGRADE)->up();

        $this->assertTrue(Schema::hasIndex('simple_cart_lists', ['owner', 'list', 'slot'], 'unique'));
    }

    public function test_down_restores_v2_schema(): void
    {
        $this->migration(self::CREATE)->up();
        $this->migration(self::UPGRADE)->up();

        CartRecord::query()->create(['owner' => 'u', 'list' => 'cart', 'payload' => ['lines' => []]]);
        CartRecord::query()->create(['owner' => 'u', 'list' => 'cart', 'payload' => ['lines' => []], 'slot' => '1', 'status' => ListStatus::Ordered]);

        $this->migration(self::UPGRADE)->down();

        $this->assertFalse(Schema::hasColumn('simple_cart_lists', 'slot'));
        $this->assertTrue(Schema::hasIndex('simple_cart_lists', ['owner', 'list'], 'unique'));
        $this->assertSame(1, DB::table('simple_cart_lists')->count());
    }

    private function migration(string $path): Migration
    {
        return require $path;
    }
}
