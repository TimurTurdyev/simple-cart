<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\Cart\Exceptions\InvalidLineException;
use TimurTurdyev\Cart\Line;
use TimurTurdyev\Cart\Support\Price;
use TimurTurdyev\Cart\Tests\Fixtures\FakeProduct;

final class LineTest extends TestCase
{
    public function test_identity_is_stable_regardless_of_option_order(): void
    {
        $a = Line::identity(11, ['color' => 'red', 'size' => 'M']);
        $b = Line::identity(11, ['size' => 'M', 'color' => 'red']);

        $this->assertSame($a, $b);
    }

    public function test_different_options_produce_different_identity(): void
    {
        $red = Line::of(11, 'Shirt', Price::fromMinor(1000), options: ['color' => 'red']);
        $blue = Line::of(11, 'Shirt', Price::fromMinor(1000), options: ['color' => 'blue']);

        $this->assertNotSame($red->id, $blue->id);
    }

    public function test_for_reads_purchasable(): void
    {
        $line = Line::for(new FakeProduct(id: 7, name: 'Mug', price: 500), quantity: 2);

        $this->assertSame(7, $line->purchasableId);
        $this->assertSame(FakeProduct::class, $line->purchasableType);
        $this->assertSame('Mug', $line->name);
        $this->assertSame(500, $line->price->minor());
        $this->assertSame(2, $line->quantity);
    }

    public function test_purchasable_type_affects_identity(): void
    {
        $plain = Line::of(7, 'Mug', Price::fromMinor(500));
        $typed = Line::for(new FakeProduct(id: 7, name: 'Mug', price: 500));

        $this->assertNotSame($plain->id, $typed->id);
    }

    public function test_subtotal(): void
    {
        $line = Line::of(1, 'Item', Price::fromMinor(1999), quantity: 3);

        $this->assertSame(5997, $line->subtotal()->minor());
    }

    public function test_quantity_helpers(): void
    {
        $line = Line::of(1, 'Item', Price::fromMinor(100), quantity: 2);

        $this->assertSame(5, $line->withQuantity(5)->quantity);
        $this->assertSame(3, $line->addQuantity(1)->quantity);
        $this->assertSame(2, $line->quantity);
    }

    public function test_option_accessor(): void
    {
        $line = Line::of(1, 'Item', Price::fromMinor(100), options: ['color' => 'red']);

        $this->assertSame('red', $line->option('color'));
        $this->assertSame('fallback', $line->option('size', 'fallback'));
    }

    public function test_array_roundtrip(): void
    {
        $line = Line::for(new FakeProduct(), quantity: 2, options: ['color' => 'red']);

        $restored = Line::fromArray($line->toArray());

        $this->assertEquals($line, $restored);
    }

    public function test_rejects_empty_name(): void
    {
        $this->expectException(InvalidLineException::class);

        Line::of(1, '  ', Price::fromMinor(100));
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidLineException::class);

        Line::of(1, 'Item', Price::fromMinor(100), quantity: 0);
    }
}
