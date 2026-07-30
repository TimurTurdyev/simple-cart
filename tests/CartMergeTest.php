<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\Cart\Adjusters\PercentageDiscount;
use TimurTurdyev\Cart\Cart;
use TimurTurdyev\Cart\Line;
use TimurTurdyev\Cart\MergeStrategy;
use TimurTurdyev\Cart\Support\Price;

final class CartMergeTest extends TestCase
{
    public function test_sum_strategy_adds_quantities(): void
    {
        $shared = $this->line(1, quantity: 2);
        $user = Cart::make()->add($shared)->add($this->line(2));
        $guest = Cart::make()->add($this->line(1, quantity: 3))->add($this->line(3));

        $merged = $user->merge($guest, MergeStrategy::Sum);

        $this->assertSame(3, $merged->count());
        $this->assertSame(5, $merged->get($shared->id)?->quantity);
    }

    public function test_keep_strategy_prefers_user_lines(): void
    {
        $shared = $this->line(1, quantity: 2);
        $user = Cart::make()->add($shared);
        $guest = Cart::make()->add($this->line(1, quantity: 9))->add($this->line(2));

        $merged = $user->merge($guest, MergeStrategy::Keep);

        $this->assertSame(2, $merged->count());
        $this->assertSame(2, $merged->get($shared->id)?->quantity);
    }

    public function test_replace_strategy_takes_guest_state(): void
    {
        $user = Cart::make()->add($this->line(1))->adjust(new PercentageDiscount('user', 5));
        $guest = Cart::make()->add($this->line(2, quantity: 4));

        $merged = $user->merge($guest, MergeStrategy::Replace);

        $this->assertSame(1, $merged->count());
        $this->assertSame(4, $merged->totalQuantity());
        $this->assertSame([], $merged->adjusters);
    }

    public function test_guest_adjusters_added_when_missing(): void
    {
        $user = Cart::make()->add($this->line(1))->adjust(new PercentageDiscount('summer', 5));
        $guest = Cart::make()->adjust(
            new PercentageDiscount('summer', 50),
            new PercentageDiscount('promo', 10),
        );

        $merged = $user->merge($guest, MergeStrategy::Sum);

        $this->assertSame(['summer', 'promo'], array_keys($merged->adjusters));
        $this->assertEquals(new PercentageDiscount('summer', 5), $merged->adjusters['summer']);
    }

    private function line(int $id, int $quantity = 1): Line
    {
        return Line::of($id, "Item {$id}", Price::fromMinor(100), $quantity);
    }
}
