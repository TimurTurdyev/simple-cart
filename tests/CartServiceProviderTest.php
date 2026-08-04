<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

final class CartServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('session', config('simple_cart.storage'));
        $this->assertSame('append', config('simple_cart.lists.cart.policy'));
        $this->assertSame('toggle', config('simple_cart.lists.wishlist.policy'));
        $this->assertSame(4, config('simple_cart.lists.compare.limit'));
        $this->assertTrue(config('simple_cart.events'));
    }
}
