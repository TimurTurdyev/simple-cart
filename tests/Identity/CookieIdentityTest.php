<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Identity;

use Closure;
use Illuminate\Contracts\Cookie\QueueingFactory;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Identity\CookieIdentity;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class CookieIdentityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cart.storage', 'database');
        $app['config']->set('cart.identity.driver', 'cookie');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_generates_stable_id_when_cookie_is_missing(): void
    {
        $identity = $this->identity(fn (): ?string => null);

        $id = $identity->id();

        $this->assertNotSame('', $id);
        $this->assertSame($id, $identity->id());
    }

    public function test_reads_existing_cookie_value(): void
    {
        $identity = $this->identity(fn (): ?string => 'existing-id');

        $this->assertSame('existing-id', $identity->id());
    }

    public function test_persist_queues_cookie_with_configured_name_and_ttl(): void
    {
        $jar = $this->app->make('cookie');
        $identity = $this->identity(fn (): ?string => null, $jar);

        $identity->persist();

        $queued = $jar->queued('cart_id');

        $this->assertNotNull($queued);
        $this->assertSame($identity->id(), $queued->getValue());
        $this->assertGreaterThan(time() + 60 * 60 * 24 * 29, $queued->getExpiresTime());
    }

    public function test_persist_does_not_queue_twice(): void
    {
        $jar = new CountingCookieJar($this->app->make('cookie'));
        $identity = $this->identity(fn (): ?string => null, $jar);

        $identity->persist();
        $identity->persist();

        $this->assertSame(1, $jar->queueCalls);
    }

    public function test_cookie_is_queued_only_on_first_write(): void
    {
        $jar = $this->app->make('cookie');
        $list = $this->app->make(CartManager::class)->list('cart');

        $list->items();

        $this->assertNull($jar->queued('cart_id'));

        $list->add(new FakeProduct(price: 500));

        $queued = $jar->queued('cart_id');

        $this->assertNotNull($queued);
        $this->assertSame(1, CartRecord::query()->where('owner', $queued->getValue())->count());
    }

    private function identity(Closure $cookieValue, ?QueueingFactory $jar = null): CookieIdentity
    {
        return new CookieIdentity(
            cookies: $jar ?? $this->app->make('cookie'),
            cookieValue: $cookieValue,
            name: 'cart_id',
            ttlMinutes: 60 * 24 * 30,
        );
    }
}

final class CountingCookieJar implements QueueingFactory
{
    public int $queueCalls = 0;

    public function __construct(private readonly QueueingFactory $inner)
    {
    }

    public function make($name, $value, $minutes = 0, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null)
    {
        return $this->inner->make($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    public function forever($name, $value, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null)
    {
        return $this->inner->forever($name, $value, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    public function forget($name, $path = null, $domain = null)
    {
        return $this->inner->forget($name, $path, $domain);
    }

    public function queue(...$parameters)
    {
        $this->queueCalls++;

        $this->inner->queue(...$parameters);
    }

    public function unqueue($name, $path = null)
    {
        $this->inner->unqueue($name, $path);
    }

    public function getQueuedCookies()
    {
        return $this->inner->getQueuedCookies();
    }
}
