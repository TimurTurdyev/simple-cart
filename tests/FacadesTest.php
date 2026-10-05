<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use TimurTurdyev\SimpleCart\Exceptions\ListLimitException;
use TimurTurdyev\SimpleCart\Facades\Cart;
use TimurTurdyev\SimpleCart\Facades\Compare;
use TimurTurdyev\SimpleCart\Facades\Wishlist;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class FacadesTest extends TestCase
{
    public function test_cart_facade(): void
    {
        Cart::add(new FakeProduct(price: 1000), quantity: 2);

        $this->assertSame(2000, Cart::total()->minor());
        $this->assertSame(2, Cart::totalQuantity());
    }

    public function test_wishlist_facade_toggle_and_move(): void
    {
        $product = new FakeProduct(price: 500);

        Wishlist::toggle($product);
        $this->assertTrue(Wishlist::has($product));

        Wishlist::moveToCart($product);

        $this->assertTrue(Wishlist::isEmpty());
        $this->assertTrue(Cart::has($product));
    }

    public function test_compare_facade_respects_limit(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            Compare::add(new FakeProduct(id: $i));
        }

        $this->expectException(ListLimitException::class);

        Compare::add(new FakeProduct(id: 5));
    }
}
