<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

final class CartServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('session', config('cart.storage'));
        $this->assertSame('append', config('cart.lists.cart.policy'));
        $this->assertSame('toggle', config('cart.lists.wishlist.policy'));
        $this->assertSame(4, config('cart.lists.compare.limit'));
        $this->assertTrue(config('cart.events'));
    }
}
