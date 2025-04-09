<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Legacy;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\Cart\Adjusters\FixedDiscount;
use TimurTurdyev\Cart\Adjusters\PercentageDiscount;
use TimurTurdyev\Cart\Adjusters\PercentageFee;
use TimurTurdyev\Cart\Adjusters\Shipping;
use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Legacy\LegacyImporter;
use TimurTurdyev\Cart\ListPolicy;

final class LegacyImporterTest extends TestCase
{
    public function test_converts_items(): void
    {
        $lines = (new LegacyImporter())->lines([
            [
                'id' => 11,
                'name' => 'Shirt',
                'price' => 19.99,
                'quantity' => 2,
                'attributes' => ['color' => 'red'],
                'associatedModel' => 'App\\Models\\Product',
            ],
        ]);

        $this->assertCount(1, $lines);
        $this->assertSame(1999, $lines[0]['price']);
        $this->assertSame(['color' => 'red'], $lines[0]['options']);
        $this->assertSame('App\\Models\\Product', $lines[0]['purchasable_type']);
        $this->assertSame(2, $lines[0]['quantity']);
    }

    public function test_integer_legacy_price_means_whole_units(): void
    {
        $lines = (new LegacyImporter())->lines([
            ['id' => 1, 'name' => 'Item', 'price' => 20, 'quantity' => 1],
        ]);

        $this->assertSame(2000, $lines[0]['price']);
    }

    public function test_skips_incomplete_items(): void
    {
        $lines = (new LegacyImporter())->lines([
            ['id' => 1, 'name' => 'No price', 'quantity' => 1],
            'garbage',
        ]);

        $this->assertSame([], $lines);
    }

    public function test_condition_mapping(): void
    {
        $adjusters = (new LegacyImporter())->adjusters([
            ['name' => 'sale', 'value' => '-10%'],
            ['name' => 'vat', 'value' => '20%'],
            ['name' => 'coupon', 'value' => '-5.50'],
            ['name' => 'delivery', 'value' => '+125'],
            ['name' => 'broken', 'value' => 'abc'],
        ]);

        $this->assertSame(
            [PercentageDiscount::class, PercentageFee::class, FixedDiscount::class, Shipping::class],
            array_column($adjusters, 'class'),
        );
        $this->assertSame(550, $adjusters[2]['data']['amount']);
        $this->assertSame(12500, $adjusters[3]['data']['amount']);
    }

    public function test_payload_matches_legacy_math(): void
    {
        $payload = (new LegacyImporter())->payload([
            'items' => [['id' => 1, 'name' => 'Item', 'price' => 100.0, 'quantity' => 1]],
            'conditions' => [['name' => 'sale', 'value' => '-10%']],
        ]);

        $cart = Cart::fromArray(new ListPolicy(), $payload);

        $this->assertSame(10000, $cart->subtotal()->minor());
        $this->assertSame(9000, $cart->total()->minor());
    }
}
