<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Support;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final class TotalsTest extends TestCase
{
    public function test_total_without_adjustments_equals_subtotal(): void
    {
        $totals = Totals::of(Price::fromMinor(5000));

        $this->assertSame(5000, $totals->total()->minor());
    }

    public function test_discount_and_fee(): void
    {
        $totals = Totals::of(Price::fromMinor(10000))
            ->addDiscount('summer', Price::fromMinor(1000))
            ->addFee('shipping', Price::fromMinor(500));

        $this->assertSame(9500, $totals->total()->minor());
        $this->assertSame(-1000, $totals->adjustment('summer')->minor());
        $this->assertSame(500, $totals->adjustment('shipping')->minor());
        $this->assertNull($totals->adjustment('unknown'));
    }

    public function test_total_is_never_negative(): void
    {
        $totals = Totals::of(Price::fromMinor(1000))
            ->addDiscount('huge', Price::fromMinor(2000));

        $this->assertSame(0, $totals->total()->minor());
    }

    public function test_breakdown(): void
    {
        $totals = Totals::of(Price::fromMinor(10000))
            ->addDiscount('summer', Price::fromMinor(1000));

        $breakdown = $totals->breakdown();

        $this->assertSame(10000, $breakdown['subtotal']->minor());
        $this->assertSame(['summer'], array_keys($breakdown['adjustments']));
        $this->assertSame(9000, $breakdown['total']->minor());
    }

    public function test_adjustments_preserve_order(): void
    {
        $totals = Totals::of(Price::fromMinor(10000))
            ->addFee('shipping', Price::fromMinor(500))
            ->addDiscount('summer', Price::fromMinor(1000));

        $this->assertSame(['shipping', 'summer'], array_keys($totals->adjustments));
    }

    public function test_add_methods_are_immutable(): void
    {
        $original = Totals::of(Price::fromMinor(1000));
        $original->addFee('shipping', Price::fromMinor(500));

        $this->assertSame([], $original->adjustments);
        $this->assertSame(1000, $original->total()->minor());
    }
}
