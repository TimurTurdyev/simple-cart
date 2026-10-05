<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\DB;
use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class UpgradeScenarioTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
    }

    public function test_v2_cart_survives_upgrade_and_works_with_v3_api(): void
    {
        (require __DIR__.'/../database/migrations/2024_01_01_000001_create_simple_cart_lists_table.php')->up();

        $v2Line = Line::of(7, 'Box', Price::fromMinor(1500), 2, ['size' => 'M'], FakeProduct::class);
        DB::table('simple_cart_lists')->insert([
            'owner' => 'guest-1',
            'list' => 'cart',
            'payload' => json_encode([
                'lines' => [$v2Line->toArray()],
                'adjusters' => [['class' => PercentageDiscount::class, 'data' => ['name' => 'summer', 'percent' => 10]]],
            ]),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        (require __DIR__.'/../database/migrations/2026_10_05_000001_upgrade_simple_cart_lists_to_v3.php')->up();

        $cart = $this->cart();
        $this->assertSame(1, $cart->count());
        $this->assertSame(2, $cart->get($v2Line->id)->quantity);
        $this->assertSame(2700, $cart->total()->minor());

        $cart->add(new FakeProduct(id: 8, price: 500));
        $this->assertSame(1, $cart->reprice(fn (Line $line): ?Price => $line->purchasableId === 7 ? Price::fromMinor(1400) : null));
        $cart->setAttribute('region', 77);

        $reloaded = $this->cart();
        $this->assertTrue($reloaded->get($v2Line->id)->priceChanged());
        $this->assertSame(77, $reloaded->attribute('region'));
        $this->assertSame(3, CartRecord::query()->value('version'));

        $snapshot = $reloaded->checkout('ORD-1');
        $this->assertSame(2, $snapshot->count());
        $this->assertSame(['region' => 77], $snapshot->attributes);
        $this->assertSame(ListStatus::Ordered, CartRecord::query()->value('status'));

        $this->artisan('simple-cart:prune', ['--dry-run' => true])
            ->expectsOutput('Would expire 0 idle list(s).')
            ->assertSuccessful();
    }

    private function cart(): ManagedList
    {
        return (new CartManager(
            storage: new DatabaseStorage(new FixedIdentity('guest-1')),
            config: $this->app['config'],
            events: $this->app['events'],
        ))->list('cart');
    }
}
