<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\LineRepriced;
use TimurTurdyev\SimpleCart\Exceptions\InvalidLineException;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\Fixtures\PricedModel;

final class RepriceTest extends TestCase
{
    public function test_line_remembers_price_seen_before_first_change(): void
    {
        $line = Line::of(1, 'Box', Price::fromMinor(1000));

        $up = $line->withPrice(Price::fromMinor(1200));
        $again = $up->withPrice(Price::fromMinor(1300));

        $this->assertFalse($line->priceChanged());
        $this->assertTrue($up->priceChanged());
        $this->assertSame(1000, $again->previousPrice->minor());
        $this->assertSame(1300, $again->price->minor());
        $this->assertSame($line, $line->withPrice(Price::fromMinor(1000)));
    }

    public function test_returning_to_seen_price_clears_mark(): void
    {
        $line = Line::of(1, 'Box', Price::fromMinor(1000))
            ->withPrice(Price::fromMinor(1200))
            ->withPrice(Price::fromMinor(1000));

        $this->assertFalse($line->priceChanged());
    }

    public function test_acknowledge_clears_mark(): void
    {
        $line = Line::of(1, 'Box', Price::fromMinor(1000))->withPrice(Price::fromMinor(1200))->acknowledgePrice();

        $this->assertFalse($line->priceChanged());
        $this->assertSame(1200, $line->price->minor());
    }

    public function test_previous_price_survives_payload_and_quantity_change(): void
    {
        $line = Line::of(1, 'Box', Price::fromMinor(1000))->withPrice(Price::fromMinor(1200))->withQuantity(3);
        $restored = Line::fromArray($line->toArray());

        $this->assertSame(1000, $restored->previousPrice->minor());
        $this->assertArrayNotHasKey('previous_price', Line::of(1, 'Box', Price::fromMinor(1))->toArray());
    }

    public function test_broken_previous_price_is_a_line_error(): void
    {
        $this->expectException(InvalidLineException::class);

        Line::fromArray([...Line::of(1, 'Box', Price::fromMinor(1))->toArray(), 'previous_price' => '1000']);
    }

    public function test_reprice_keeps_line_and_dispatches_event(): void
    {
        Event::fake([LineRepriced::class]);
        $cart = $this->cart();
        $line = $cart->add(new FakeProduct(id: 1, price: 1000), quantity: 2, options: ['size' => 'M'], meta: ['sku' => 'A']);
        $cart->add(new FakeProduct(id: 2, price: 500));

        $changed = $cart->reprice(fn (Line $line): ?Price => $line->purchasableId === 1 ? Price::fromMinor(1200) : null);

        $repriced = $this->cart()->get($line->id);
        $this->assertSame(1, $changed);
        $this->assertSame([$line->id, 2, ['size' => 'M'], ['sku' => 'A']], [$repriced->id, $repriced->quantity, $repriced->options, $repriced->meta]);
        $this->assertSame(1200, $repriced->price->minor());
        $this->assertTrue($repriced->priceChanged());
        $this->assertSame(2900, $this->cart()->subtotal()->minor());
        Event::assertDispatched(LineRepriced::class, fn (LineRepriced $e): bool => $e->line->id === $line->id && $e->previous->minor() === 1000);
        Event::assertDispatchedTimes(LineRepriced::class, 1);
    }

    public function test_same_price_is_not_a_change(): void
    {
        $cart = $this->cart();
        $cart->add(new FakeProduct(price: 1000));

        $this->assertSame(0, $cart->reprice(fn (): Price => Price::fromMinor(1000)));
        $this->assertFalse($cart->items()->first()->priceChanged());
    }

    public function test_reprice_without_resolver_uses_model_prices(): void
    {
        PricedModel::$prices = [7 => 1000];
        $cart = $this->cart();
        $line = $cart->add(new PricedModel(7));

        PricedModel::$prices = [7 => 900];

        $this->assertSame(1, $cart->reprice());
        $this->assertSame(900, $cart->get($line->id)->price->minor());
        $this->assertSame(1000, $cart->get($line->id)->previousPrice->minor());
    }

    public function test_reprice_line_and_acknowledge(): void
    {
        $cart = $this->cart();
        $line = $cart->add(new FakeProduct(price: 1000));

        $cart->repriceLine($line->id, Price::fromMinor(800));
        $this->assertTrue($this->cart()->get($line->id)->priceChanged());

        $cart->acknowledgePrices();
        $this->assertFalse($this->cart()->get($line->id)->priceChanged());
        $this->assertSame(800, $this->cart()->get($line->id)->price->minor());
    }

    public function test_reprice_unknown_line_throws(): void
    {
        $this->expectException(UnknownLineException::class);

        $this->cart()->repriceLine('missing', Price::fromMinor(1));
    }

    private function cart(): ManagedList
    {
        return $this->app->make(CartManager::class)->list('cart');
    }
}
