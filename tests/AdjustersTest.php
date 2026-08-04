<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\SimpleCart\Adjusters\FixedDiscount;
use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\Adjusters\PercentageFee;
use TimurTurdyev\SimpleCart\Adjusters\Shipping;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;
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

    public function test_percentage_fee(): void
    {
        $totals = (new PercentageFee('service', 10))->adjust(Totals::of(Price::fromMinor(2000)));

        $this->assertSame(200, $totals->adjustment('service')->minor());
        $this->assertSame(2200, $totals->total()->minor());
    }

    public function test_serialization_roundtrip(): void
    {
        $adjusters = [
            new PercentageDiscount('summer', 12.5),
            new PercentageFee('service', 5.0),
            new FixedDiscount('bonus', Price::fromMinor(500)),
            new Shipping(Price::fromMinor(1500), 'delivery'),
        ];

        foreach ($adjusters as $adjuster) {
            $restored = $adjuster::fromArray($adjuster->toArray());

            $this->assertEquals($adjuster, $restored);
        }
    }

    public function test_from_array_rejects_empty_payload(): void
    {
        $classes = [PercentageDiscount::class, PercentageFee::class, FixedDiscount::class, Shipping::class];

        foreach ($classes as $class) {
            try {
                $class::fromArray([]);
                $this->fail("{$class} accepted an empty payload.");
            } catch (InvalidAdjusterException $exception) {
                $this->assertStringContainsString('missing', $exception->getMessage());
            }
        }
    }

    public function test_from_array_rejects_missing_key(): void
    {
        $this->expectException(InvalidAdjusterException::class);
        $this->expectExceptionMessage('percent');

        PercentageDiscount::fromArray(['name' => 'summer']);
    }

    public function test_from_array_rejects_garbage_types(): void
    {
        $payloads = [
            [PercentageDiscount::class, ['name' => 'summer', 'percent' => 'abc']],
            [PercentageFee::class, ['name' => 'service', 'percent' => [10]]],
            [FixedDiscount::class, ['name' => 'bonus', 'amount' => '500']],
            [FixedDiscount::class, ['name' => 42, 'amount' => 500]],
            [Shipping::class, ['amount' => 15.5]],
            [Shipping::class, ['amount' => 1500, 'name' => ['delivery']]],
        ];

        foreach ($payloads as [$class, $payload]) {
            try {
                $class::fromArray($payload);
                $this->fail("{$class} accepted a garbage payload.");
            } catch (InvalidAdjusterException $exception) {
                $this->assertStringContainsString('invalid', $exception->getMessage());
            }
        }
    }

    public function test_shipping_from_array_defaults_name(): void
    {
        $shipping = Shipping::fromArray(['amount' => 1500]);

        $this->assertSame('shipping', $shipping->name());
    }
}
