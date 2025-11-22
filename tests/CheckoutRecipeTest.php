<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\Adjusters\Shipping;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\ListCleared;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class CheckoutRecipeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
    }

    public function test_readme_checkout_recipe(): void
    {
        Event::fake();

        $cart = $this->app->make(CartManager::class)->list('cart');
        $cart->add(new FakeProduct(price: 2999), quantity: 2, options: ['size' => 'M']);
        $cart->adjust(
            new PercentageDiscount('summer', percent: 10),
            new Shipping(Price::fromMinor(500)),
        );

        $lines = $cart->items()->map(fn (Line $line): array => $line->toArray())->values()->all();
        $breakdown = $cart->totals()->breakdown();

        $order = [
            'lines' => $lines,
            'subtotal' => $breakdown['subtotal']->minor(),
            'adjustments' => array_map(
                fn (Price $amount): int => $amount->minor(),
                $breakdown['adjustments'],
            ),
            'total' => $breakdown['total']->minor(),
        ];

        $cart->clear();

        $this->assertCount(1, $order['lines']);
        $this->assertSame(2999, $order['lines'][0]['price']);
        $this->assertSame(2, $order['lines'][0]['quantity']);
        $this->assertSame(['size' => 'M'], $order['lines'][0]['options']);
        $this->assertSame(5998, $order['subtotal']);
        $this->assertSame(['summer' => -600, 'shipping' => 500], $order['adjustments']);
        $this->assertSame(5898, $order['total']);
        $this->assertSame(
            $order['total'],
            $order['subtotal'] + array_sum($order['adjustments']),
        );

        $this->assertTrue($cart->isEmpty());
        Event::assertDispatched(ListCleared::class, fn (ListCleared $e): bool => $e->list === 'cart');
    }
}
