<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use TimurTurdyev\SimpleCart\Cart;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Exceptions\InvalidLineException;
use TimurTurdyev\SimpleCart\Exceptions\QuantityRuleException;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\MergeStrategy;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\QuantityRule;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\Fixtures\PackedProduct;

final class QuantityRulesTest extends TestCase
{
    public function test_add_and_set_reject_quantities_off_the_rule(): void
    {
        $cart = $this->cart();
        $product = new PackedProduct(min: 2, step: 4, max: 14);

        $line = $cart->add($product, quantity: 6);

        $this->assertViolation(fn () => $cart->add(new PackedProduct(id: 2, min: 2, step: 4), quantity: 3), 3);
        $this->assertViolation(fn () => $cart->setQuantity($line->id, 8), 8);
        $this->assertViolation(fn () => $cart->setQuantity($line->id, 18), 18);
        $this->assertViolation(fn () => $cart->add($product, quantity: 1), 7);
        $this->assertSame(6, $cart->get($line->id)->quantity);
    }

    public function test_violation_carries_rule_for_ui(): void
    {
        try {
            $this->cart()->add(new PackedProduct(min: 66, step: 66, max: 660), quantity: 70);
            $this->fail('Expected QuantityRuleException.');
        } catch (QuantityRuleException $e) {
            $this->assertSame([66, 66, 660, 70], [$e->min, $e->step, $e->max, $e->given]);
        }
    }

    public function test_add_without_quantity_uses_min_then_step(): void
    {
        $cart = $this->cart();
        $product = new PackedProduct(min: 5, step: 5);

        $line = $cart->add($product);
        $this->assertSame(5, $line->quantity);

        $this->assertSame(10, $cart->add($product)->quantity);
        $this->assertSame(1, $cart->add(new FakeProduct(id: 9))->quantity);
    }

    public function test_list_default_rule_from_config(): void
    {
        config()->set('simple_cart.lists.cart.quantity', ['min' => 3, 'step' => 3]);

        $cart = $this->cart();

        $this->assertSame(3, $cart->add(new FakeProduct())->quantity);
        $this->assertViolation(fn () => $cart->add(new FakeProduct(id: 2), quantity: 4), 4);
    }

    public function test_line_rule_wins_over_list_default(): void
    {
        config()->set('simple_cart.lists.cart.quantity', ['min' => 3, 'step' => 3]);

        $this->assertSame(2, $this->cart()->add(new PackedProduct(min: 2))->quantity);
    }

    public function test_toggle_lists_ignore_rules(): void
    {
        $wishlist = $this->manager()->list('wishlist');

        $this->assertTrue($wishlist->toggle(new PackedProduct(min: 5)));
        $this->assertSame(1, $wishlist->items()->first()->quantity);
    }

    public function test_move_to_cart_lifts_quantity_to_min(): void
    {
        $manager = $this->manager();
        $product = new PackedProduct(min: 5, step: 5);

        $manager->list('wishlist')->toggle($product);
        $manager->list('wishlist')->moveToCart($product);

        $this->assertSame(5, $manager->list('cart')->items()->first()->quantity);
        $this->assertTrue($manager->list('wishlist')->isEmpty());
    }

    public function test_move_into_existing_line_fits_the_sum(): void
    {
        $manager = $this->manager();
        $product = new PackedProduct(min: 2, step: 4);

        $manager->list('cart')->add($product, quantity: 6);
        $manager->list('wishlist')->toggle($product);
        $manager->list('wishlist')->moveToCart($product);

        $this->assertSame(10, $manager->list('cart')->items()->first()->quantity);
    }

    public function test_step_quantity_moves_by_rule_step(): void
    {
        $cart = $this->cart();
        $line = $cart->add(new PackedProduct(min: 4, step: 4), quantity: 8);

        $cart->stepQuantity($line->id);
        $this->assertSame(12, $cart->get($line->id)->quantity);

        $cart->stepQuantity($line->id, -1);
        $this->assertSame(8, $cart->get($line->id)->quantity);

        $cart->stepQuantity($line->id, -1);
        $this->assertSame(4, $cart->get($line->id)->quantity);

        $cart->stepQuantity($line->id, -1);
        $this->assertNull($cart->get($line->id));
    }

    public function test_step_quantity_without_rule_moves_by_one(): void
    {
        $cart = $this->cart();
        $line = $cart->add(new FakeProduct(), quantity: 1);

        $cart->stepQuantity($line->id, 2);
        $this->assertSame(3, $cart->get($line->id)->quantity);

        $cart->stepQuantity($line->id, -3);
        $this->assertNull($cart->get($line->id));
    }

    public function test_merge_fits_sum_into_rule(): void
    {
        $rule = new QuantityRule(2, 4);
        $line = fn (int $quantity, ?QuantityRule $rule) => Line::of(1, 'Box', Price::fromMinor(100), $quantity, quantityRule: $rule);

        $merged = Cart::make()->add($line(6, $rule))->merge(Cart::make()->add($line(6, $rule)), MergeStrategy::Sum);
        $this->assertSame(14, $merged->items()->first()->quantity);

        $capped = new QuantityRule(2, 4, 12);
        $merged = Cart::make()->add($line(6, $capped))->merge(Cart::make()->add($line(6, $capped)), MergeStrategy::Sum);
        $this->assertSame(10, $merged->items()->first()->quantity);
    }

    public function test_merge_fits_list_default_rule(): void
    {
        $policy = new ListPolicy(quantityRule: new QuantityRule(3, 3));
        $line = Line::of(1, 'Box', Price::fromMinor(100), 3);

        $merged = Cart::make($policy)->add($line)->merge(Cart::make($policy)->add($line->withQuantity(3)), MergeStrategy::Sum);
        $this->assertSame(6, $merged->items()->first()->quantity);

        $legacy = Cart::fromArray($policy, ['lines' => [$line->withQuantity(1)->toArray()]]);
        $this->assertSame(3, Cart::make($policy)->merge($legacy, MergeStrategy::Keep)->items()->first()->quantity);
    }

    public function test_rule_survives_payload_roundtrip(): void
    {
        $line = Line::for(new PackedProduct(min: 2, step: 4, max: 14), 6);
        $restored = Line::fromArray($line->toArray());

        $this->assertEquals(new QuantityRule(2, 4, 14), $restored->quantityRule);
        $this->assertArrayNotHasKey('quantity_rule', Line::for(new FakeProduct())->toArray());
    }

    public function test_broken_rule_in_payload_is_a_line_error(): void
    {
        $data = Line::for(new FakeProduct())->toArray();

        foreach ([['min' => 0], ['step' => '4'], 'garbage'] as $rule) {
            try {
                Line::fromArray([...$data, 'quantity_rule' => $rule]);
                $this->fail('Expected InvalidLineException.');
            } catch (InvalidLineException $e) {
                $this->assertStringContainsString('quantity_rule', $e->getMessage());
            }
        }
    }

    private function assertViolation(\Closure $operation, int $given): void
    {
        try {
            $operation();
            $this->fail('Expected QuantityRuleException.');
        } catch (QuantityRuleException $e) {
            $this->assertSame($given, $e->given);
        }
    }

    private function cart(): ManagedList
    {
        return $this->manager()->list('cart');
    }

    private function manager(): CartManager
    {
        return $this->app->make(CartManager::class);
    }
}
