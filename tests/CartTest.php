<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\Cart\Adjusters\FixedDiscount;
use TimurTurdyev\Cart\Adjusters\PercentageDiscount;
use TimurTurdyev\Cart\Adjusters\Shipping;
use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Contracts\Adjuster;
use TimurTurdyev\Cart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\Cart\Exceptions\UnknownLineException;
use TimurTurdyev\Cart\Line;
use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\Support\Price;
use TimurTurdyev\Cart\Support\Totals;

final class CartTest extends TestCase
{
    public function test_add_and_delegates(): void
    {
        $line = $this->line(1, price: 1000, quantity: 2);
        $cart = Cart::make()->add($line)->add($this->line(2, price: 500));

        $this->assertSame(2, $cart->count());
        $this->assertSame(3, $cart->totalQuantity());
        $this->assertTrue($cart->has($line->id));
        $this->assertSame($line->id, $cart->get($line->id)?->id);
        $this->assertFalse($cart->isEmpty());
        $this->assertSame(2500, $cart->subtotal()->minor());
    }

    public function test_set_quantity(): void
    {
        $line = $this->line(1);
        $cart = Cart::make()->add($line)->setQuantity($line->id, 5);

        $this->assertSame(5, $cart->totalQuantity());
    }

    public function test_set_quantity_below_one_removes_line(): void
    {
        $line = $this->line(1);
        $cart = Cart::make()->add($line)->setQuantity($line->id, 0);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_change_quantity(): void
    {
        $line = $this->line(1, quantity: 2);
        $cart = Cart::make()->add($line);

        $this->assertSame(5, $cart->changeQuantity($line->id, 3)->totalQuantity());
        $this->assertTrue($cart->changeQuantity($line->id, -2)->isEmpty());
    }

    public function test_quantity_for_unknown_line_fails(): void
    {
        $this->expectException(UnknownLineException::class);

        Cart::make()->setQuantity('missing', 2);
    }

    public function test_pipeline_totals(): void
    {
        $cart = Cart::make()
            ->add($this->line(1, price: 10000))
            ->adjust(
                new PercentageDiscount('summer', 10),
                new Shipping(Price::fromMinor(1500)),
            );

        $this->assertSame(10000, $cart->subtotal()->minor());
        $this->assertSame(10500, $cart->total()->minor());
        $this->assertSame(-1000, $cart->totals()->adjustment('summer')->minor());
    }

    public function test_sequential_percentage_discounts_compound(): void
    {
        $cart = Cart::make()
            ->add($this->line(1, price: 10000))
            ->adjust(new PercentageDiscount('first', 10), new PercentageDiscount('second', 10));

        $this->assertSame(8100, $cart->total()->minor());
    }

    public function test_adjust_replaces_same_name(): void
    {
        $cart = Cart::make()
            ->add($this->line(1, price: 10000))
            ->adjust(new PercentageDiscount('summer', 10))
            ->adjust(new PercentageDiscount('summer', 20));

        $this->assertCount(1, $cart->adjusters);
        $this->assertSame(8000, $cart->total()->minor());
    }

    public function test_without_adjuster(): void
    {
        $cart = Cart::make()
            ->add($this->line(1, price: 10000))
            ->adjust(new PercentageDiscount('summer', 10))
            ->withoutAdjuster('summer');

        $this->assertSame(10000, $cart->total()->minor());
    }

    public function test_total_is_never_negative(): void
    {
        $cart = Cart::make()
            ->add($this->line(1, price: 1000))
            ->adjust(new FixedDiscount('huge', Price::fromMinor(5000)));

        $this->assertSame(0, $cart->total()->minor());
    }

    public function test_custom_adjuster_class(): void
    {
        $giftWrap = new class implements Adjuster
        {
            public function name(): string
            {
                return 'gift-wrap';
            }

            public function adjust(Totals $totals): Totals
            {
                return $totals->addFee($this->name(), Price::fromMinor(300));
            }

            public function toArray(): array
            {
                return [];
            }

            public static function fromArray(array $data): static
            {
                return new static();
            }
        };

        $cart = Cart::make()->add($this->line(1, price: 1000))->adjust($giftWrap);

        $this->assertSame(1300, $cart->total()->minor());
    }

    public function test_clear_resets_lines_and_adjusters(): void
    {
        $cart = Cart::make()
            ->add($this->line(1))
            ->adjust(new PercentageDiscount('summer', 10))
            ->clear();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame([], $cart->adjusters);
    }

    public function test_array_roundtrip_restores_adjusters(): void
    {
        $policy = new ListPolicy();
        $cart = Cart::make($policy)
            ->add($this->line(1, price: 10000, quantity: 2))
            ->adjust(new PercentageDiscount('summer', 10), new Shipping(Price::fromMinor(500)));

        $restored = Cart::fromArray($policy, $cart->toArray());

        $this->assertSame($cart->total()->minor(), $restored->total()->minor());
        $this->assertSame(['summer', 'shipping'], array_keys($restored->adjusters));
    }

    public function test_from_array_rejects_non_adjuster_class(): void
    {
        $this->expectException(InvalidAdjusterException::class);

        Cart::fromArray(new ListPolicy(), [
            'lines' => [],
            'adjusters' => [['class' => \stdClass::class, 'data' => []]],
        ]);
    }

    private function line(int $id, int $price = 100, int $quantity = 1): Line
    {
        return Line::of($id, "Item {$id}", Price::fromMinor($price), $quantity);
    }
}
