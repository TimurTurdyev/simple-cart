<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;

final class AuthOwnerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('simple_cart.identity.driver', 'session');
        $app['config']->set('auth.guards.customer', ['driver' => 'session', 'provider' => 'users']);
    }

    public function test_defaults_keep_v3_0_owner(): void
    {
        $this->actingAs(new GenericUser(['id' => 5]));

        $this->cart()->add(new FakeProduct());

        $this->assertSame('5', CartRecord::query()->value('owner'));
        $this->assertSame('5', $this->identities()->ownerFor(5));
    }

    public function test_cart_guard_and_prefix_make_the_owner(): void
    {
        $this->useCustomerGuard();
        $this->actingAs(new GenericUser(['id' => 5]), 'customer');

        $this->cart()->add(new FakeProduct());

        $this->assertSame('customer:5', CartRecord::query()->value('owner'));
    }

    public function test_user_of_another_guard_shops_as_guest(): void
    {
        $this->useCustomerGuard();
        $this->actingAs(new GenericUser(['id' => 5]), 'web');

        $this->cart()->add(new FakeProduct());

        $this->assertSame($this->app->make('session.store')->getId(), CartRecord::query()->value('owner'));
    }

    public function test_merge_runs_only_for_the_cart_guard_and_uses_prefix(): void
    {
        $this->useCustomerGuard();
        $this->cart()->add(new FakeProduct(), quantity: 2);
        $guest = $this->app->make('session.store')->getId();

        event(new Login('web', new GenericUser(['id' => 5]), false));
        $this->assertSame(1, CartRecord::query()->active()->owner($guest)->count());

        event(new Login('customer', new GenericUser(['id' => 5]), false));
        $this->assertSame(ListStatus::Merged, CartRecord::query()->owner($guest)->value('status'));
        $this->assertSame('customer:5', CartRecord::query()->owner($guest)->value('reference'));
        $this->assertSame(2, CartRecord::query()->active()->owner('customer:5')->sole()->cart()->totalQuantity());
    }

    public function test_admin_finds_customer_cart_by_user(): void
    {
        $this->useCustomerGuard();
        $customer = new GenericUser(['id' => 5]);
        $this->actingAs($customer, 'customer');
        $this->cart()->add(new FakeProduct(price: 700));

        $owner = $this->identities()->ownerFor($customer);

        $this->assertSame('customer:5', $owner);
        $this->assertSame(700, CartRecord::query()->active()->owner($owner)->sole()->cart()->total()->minor());
    }

    private function useCustomerGuard(): void
    {
        config()->set('simple_cart.identity.auth.guard', 'customer');
        config()->set('simple_cart.identity.auth.prefix', 'customer:');
    }

    private function cart(): ManagedList
    {
        return $this->app->make(CartManager::class)->list('cart');
    }

    private function identities(): IdentityManager
    {
        return $this->app->make(IdentityManager::class);
    }
}
