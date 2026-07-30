<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Storage;

use TimurTurdyev\Cart\Storage\SessionStorage;
use TimurTurdyev\Cart\Tests\TestCase;

final class SessionStorageTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
    }

    public function test_read_missing_list_returns_empty_array(): void
    {
        $this->assertSame([], $this->storage()->read('cart'));
    }

    public function test_write_and_read_roundtrip(): void
    {
        $storage = $this->storage();
        $payload = ['lines' => [['id' => 'abc', 'quantity' => 2]]];

        $storage->write('cart', $payload);

        $this->assertSame($payload, $storage->read('cart'));
    }

    public function test_lists_are_isolated(): void
    {
        $storage = $this->storage();

        $storage->write('cart', ['lines' => ['a']]);
        $storage->write('wishlist', ['lines' => ['b']]);

        $this->assertSame(['lines' => ['a']], $storage->read('cart'));
        $this->assertSame(['lines' => ['b']], $storage->read('wishlist'));
    }

    public function test_empty_write_leaves_no_record(): void
    {
        $storage = $this->storage();

        $storage->write('cart', ['lines' => ['a']]);
        $storage->write('cart', []);

        $this->assertFalse(session()->has('cart.cart'));
    }

    public function test_forget_removes_record(): void
    {
        $storage = $this->storage();

        $storage->write('cart', ['lines' => ['a']]);
        $storage->forget('cart');

        $this->assertSame([], $storage->read('cart'));
        $this->assertFalse(session()->has('cart.cart'));
    }

    private function storage(): SessionStorage
    {
        return new SessionStorage($this->app->make('session.store'));
    }
}
