<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Support\Facades\Event;
use TimurTurdyev\SimpleCart\Cart;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Events\ListAttributesUpdated;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAttributeException;
use TimurTurdyev\SimpleCart\Identity\FixedIdentity;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\MergeStrategy;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Storage\DatabaseStorage;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class AttributesTest extends TestCase
{
    public function test_attributes_roundtrip_through_payload(): void
    {
        $cart = Cart::make()->withAttributes([
            'contact' => ['phone' => '+7 900 000-00-00', 'email' => null],
            'region' => 77,
            'utm' => ['source' => 'ya', 'tags' => ['a', 'b']],
        ]);

        $restored = Cart::fromArray(new ListPolicy(), $cart->toArray());

        $this->assertSame($cart->attributes, $restored->attributes);
        $this->assertArrayNotHasKey('attributes', Cart::make()->toArray());
    }

    public function test_objects_and_bad_keys_are_rejected(): void
    {
        foreach ([[['x' => new \stdClass()]], [[0 => 'x']], [['' => 'x']], [['x' => [[fn () => 1]]]]] as [$values]) {
            try {
                Cart::make()->withAttributes($values);
                $this->fail('Expected InvalidAttributeException.');
            } catch (InvalidAttributeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_malformed_payload_section_is_rejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        Cart::fromArray(new ListPolicy(), ['lines' => [], 'attributes' => 'garbage']);
    }

    public function test_list_with_attributes_only_is_stored_and_events_name_changed_keys(): void
    {
        Event::fake([ListAttributesUpdated::class]);
        $cart = $this->cart();

        $cart->setAttributes(['region' => 77, 'draft' => ['name' => 'Ann']]);
        $cart->setAttribute('region', 77);
        $cart->setAttribute('region', 50);

        $fresh = $this->cart();
        $this->assertSame(50, $fresh->attribute('region'));
        $this->assertSame(['name' => 'Ann'], $fresh->attribute('draft'));
        $this->assertSame('none', $fresh->attribute('missing', 'none'));
        $this->assertSame(1, CartRecord::query()->count());

        Event::assertDispatchedTimes(ListAttributesUpdated::class, 2);
        Event::assertDispatched(ListAttributesUpdated::class, fn (ListAttributesUpdated $e): bool => $e->changed === ['region', 'draft']);
        Event::assertDispatched(ListAttributesUpdated::class, fn (ListAttributesUpdated $e): bool => $e->changed === ['region']);
    }

    public function test_forgetting_last_attribute_of_empty_list_removes_record(): void
    {
        $cart = $this->cart();
        $cart->setAttribute('region', 77);
        $cart->forgetAttribute('region');

        $this->assertSame([], $this->cart()->attributes());
        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_clear_drops_attributes(): void
    {
        $cart = $this->cart();
        $cart->add(new FakeProduct());
        $cart->setAttribute('region', 77);

        $cart->clear();

        $this->assertSame([], $this->cart()->attributes());
    }

    public function test_merge_user_attributes_win_guest_fills_gaps(): void
    {
        $user = Cart::make()->withAttributes(['region' => 77]);
        $guest = Cart::make()->withAttributes(['region' => 50, 'utm' => 'ya']);

        $this->assertSame(['region' => 77, 'utm' => 'ya'], $user->merge($guest, MergeStrategy::Sum)->attributes);
        $this->assertSame(['region' => 77, 'utm' => 'ya'], $user->merge($guest, MergeStrategy::Keep)->attributes);
        $this->assertSame(['region' => 50, 'utm' => 'ya'], $user->merge($guest, MergeStrategy::Replace)->attributes);
    }

    public function test_adjusters_and_lines_keep_attributes(): void
    {
        $cart = Cart::make()
            ->withAttributes(['region' => 77])
            ->add(Line::of(1, 'Box', Price::fromMinor(100)))
            ->setQuantity(Line::identity(1), 2);

        $this->assertSame(['region' => 77], $cart->attributes);
    }

    public function test_record_exposes_attributes_without_identity(): void
    {
        $this->cart()->setAttribute('contact', 'ann@example.com');

        $this->assertSame(['contact' => 'ann@example.com'], CartRecord::query()->sole()->attributes());
    }

    private function cart(): ManagedList
    {
        return (new CartManager(
            storage: new DatabaseStorage(new FixedIdentity('guest-1')),
            config: $this->app['config'],
            events: $this->app['events'],
        ))->list('cart');
    }
}
