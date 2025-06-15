<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class ManagedListTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
    }

    public function test_add_purchasable_with_options(): void
    {
        $cart = $this->cart();
        $product = new FakeProduct(id: 11, name: 'Shirt', price: 1999);

        $red = $cart->add($product, quantity: 2, options: ['color' => 'red']);
        $blue = $cart->add($product, options: ['color' => 'blue']);

        $this->assertNotSame($red->id, $blue->id);
        $this->assertSame(2, $cart->count());
        $this->assertSame(3, $cart->totalQuantity());
        $this->assertSame(5997, $cart->subtotal()->minor());
    }

    public function test_add_merges_quantities(): void
    {
        $cart = $this->cart();
        $product = new FakeProduct();

        $cart->add($product);
        $line = $cart->add($product, quantity: 2);

        $this->assertSame(3, $line->quantity);
    }

    public function test_toggle_returns_state(): void
    {
        $list = $this->manager()->list('wishlist');
        $product = new FakeProduct();

        $this->assertTrue($list->toggle($product));
        $this->assertTrue($list->has($product));
        $this->assertFalse($list->toggle($product));
        $this->assertFalse($list->has($product));
    }

    public function test_quantity_operations(): void
    {
        $cart = $this->cart();
        $line = $cart->add(new FakeProduct(), quantity: 2);

        $cart->setQuantity($line->id, 5);
        $this->assertSame(5, $cart->totalQuantity());

        $cart->changeQuantity($line->id, -5);
        $this->assertTrue($cart->isEmpty());
    }

    public function test_quantity_for_unknown_line_fails(): void
    {
        $this->expectException(UnknownLineException::class);

        $this->cart()->setQuantity('missing', 1);
    }

    public function test_adjusters_survive_reload(): void
    {
        $this->cart()->add(new FakeProduct(price: 10000));
        $this->cart()->adjust(new PercentageDiscount('summer', 10));

        $fresh = new CartManager($this->app->make(Storage::class), $this->app->make('config'));

        $this->assertSame(9000, $fresh->list()->total()->minor());
    }

    public function test_lazy_storage_record(): void
    {
        $cart = $this->cart();

        $cart->isEmpty();
        $this->assertFalse(session()->has('simple_cart.cart'));

        $line = $cart->add(new FakeProduct());
        $this->assertTrue(session()->has('simple_cart.cart'));

        $cart->remove($line->id);
        $this->assertFalse(session()->has('simple_cart.cart'));
    }

    public function test_clear_forgets_storage(): void
    {
        $cart = $this->cart();
        $cart->add(new FakeProduct());
        $cart->adjust(new PercentageDiscount('summer', 10));

        $cart->clear();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame([], $cart->adjusters());
        $this->assertFalse(session()->has('simple_cart.cart'));
    }

    public function test_move_to_cart_keeps_quantity(): void
    {
        $manager = $this->manager();
        $product = new FakeProduct(price: 500);

        $manager->list('wishlist')->add($product);
        $manager->list('wishlist')->moveToCart($product);

        $this->assertTrue($manager->list('wishlist')->isEmpty());
        $this->assertTrue($manager->list('cart')->has($product));
    }

    public function test_move_to_named_list(): void
    {
        $manager = $this->manager();
        $product = new FakeProduct();

        $line = $manager->list('cart')->add($product, quantity: 3);
        $manager->list('cart')->moveTo('wishlist', $line->id);

        $this->assertTrue($manager->list('cart')->isEmpty());
        $this->assertSame(3, $manager->list('wishlist')->get($line->id)?->quantity);
    }

    private function manager(): CartManager
    {
        return $this->app->make(CartManager::class);
    }

    private function cart(): ManagedList
    {
        return $this->manager()->list('cart');
    }
}
