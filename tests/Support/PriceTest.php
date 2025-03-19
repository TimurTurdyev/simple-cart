<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Support;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\Cart\Exceptions\InvalidPriceException;
use TimurTurdyev\Cart\Support\Price;

final class PriceTest extends TestCase
{
    public function test_from_minor(): void
    {
        $this->assertSame(1999, Price::fromMinor(1999)->minor());
        $this->assertSame(-500, Price::fromMinor(-500)->minor());
    }

    public function test_from_decimal_float(): void
    {
        $this->assertSame(1999, Price::fromDecimal(19.99)->minor());
        $this->assertSame(100, Price::fromDecimal(1.0)->minor());
        $this->assertSame(-1050, Price::fromDecimal(-10.50)->minor());
    }

    public function test_from_decimal_string(): void
    {
        $this->assertSame(1999, Price::fromDecimal('19.99')->minor());
        $this->assertSame(1000, Price::fromDecimal('10')->minor());
        $this->assertSame(1050, Price::fromDecimal('10.5')->minor());
        $this->assertSame(-1999, Price::fromDecimal('-19.99')->minor());
        $this->assertSame(1999, Price::fromDecimal('+19.99')->minor());
    }

    public function test_from_decimal_string_rounds_half_up(): void
    {
        $this->assertSame(1001, Price::fromDecimal('10.005')->minor());
        $this->assertSame(1000, Price::fromDecimal('10.004')->minor());
        $this->assertSame(1000, Price::fromDecimal('10.0049')->minor());
    }

    public function test_from_decimal_rejects_garbage(): void
    {
        $this->expectException(InvalidPriceException::class);

        Price::fromDecimal('10,50');
    }

    public function test_accessors(): void
    {
        $price = Price::fromMinor(123456);

        $this->assertSame(1234.56, $price->decimal());
        $this->assertSame('1234.56', $price->format());
        $this->assertSame('1 234,56', $price->format(',', ' '));
    }

    public function test_arithmetic(): void
    {
        $a = Price::fromMinor(1000);
        $b = Price::fromMinor(250);

        $this->assertSame(1250, $a->plus($b)->minor());
        $this->assertSame(750, $a->minus($b)->minor());
        $this->assertSame(3000, $a->times(3)->minor());
        $this->assertSame(-1000, $a->negate()->minor());
    }

    public function test_times_rounds_half_up(): void
    {
        $this->assertSame(3, Price::fromMinor(5)->times(0.5)->minor());
        $this->assertSame(333, Price::fromMinor(1000)->times(1 / 3)->minor());
    }

    public function test_percentage(): void
    {
        $this->assertSame(100, Price::fromMinor(1000)->percentage(10)->minor());
        $this->assertSame(150, Price::fromMinor(999)->percentage(15)->minor());
    }

    public function test_comparisons(): void
    {
        $this->assertTrue(Price::zero()->isZero());
        $this->assertFalse(Price::fromMinor(1)->isZero());
        $this->assertTrue(Price::fromMinor(100)->equals(Price::fromDecimal(1.0)));
        $this->assertFalse(Price::fromMinor(100)->equals(Price::fromMinor(101)));
    }
}
