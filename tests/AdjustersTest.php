<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\SimpleCart\Adjusters\FixedDiscount;
use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\Adjusters\Shipping;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final class AdjustersTest extends TestCase
{
    public function test_percentage_discount(): void
    {
        $totals = (new PercentageDiscount('summer', 10))->adjust(Totals::of(Price::fromMinor(10000)));

        $this->assertSame(-1000, $totals->adjustment('summer')->minor());
        $this->assertSame(9000, $totals->total()->minor());
    }

    public function test_percentage_discount_applies_to_running_total(): void
    {
        $totals = Totals::of(Price::fromMinor(10000));
        $totals = (new PercentageDiscount('first', 10))->adjust($totals);
        $totals = (new PercentageDiscount('second', 10))->adjust($totals);

        $this->assertSame(-900, $totals->adjustment('second')->minor());
        $this->assertSame(8100, $totals->total()->minor());
    }

    public function test_fixed_discount(): void
    {
        $totals = (new FixedDiscount('bonus', Price::fromMinor(500)))->adjust(Totals::of(Price::fromMinor(2000)));

        $this->assertSame(1500, $totals->total()->minor());
    }

    public function test_shipping_fee(): void
    {
        $totals = (new Shipping(Price::fromMinor(1500)))->adjust(Totals::of(Price::fromMinor(2000)));

        $this->assertSame(1500, $totals->adjustment('shipping')->minor());
        $this->assertSame(3500, $totals->total()->minor());
    }

    public function test_serialization_roundtrip(): void
    {
        $adjusters = [
            new PercentageDiscount('summer', 12.5),
            new FixedDiscount('bonus', Price::fromMinor(500)),
            new Shipping(Price::fromMinor(1500), 'delivery'),
        ];

        foreach ($adjusters as $adjuster) {
            $restored = $adjuster::fromArray($adjuster->toArray());

            $this->assertEquals($adjuster, $restored);
        }
    }
}
