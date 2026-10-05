<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Identity;

use Closure;
use Illuminate\Contracts\Cookie\QueueingFactory;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Exceptions\InvalidConfigurationException;
use TimurTurdyev\SimpleCart\Identity\CookieIdentity;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class CookieIdentityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('simple_cart.storage', 'database');
        $app['config']->set('simple_cart.identity.driver', 'cookie');
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
        $value = str_repeat('ab', 16);

        $identity = $this->identity(fn (): ?string => $value);

        $this->assertSame($value, $identity->id());
    }

    public function test_rejects_forged_cookie_value(): void
    {
        $identity = $this->identity(fn (): ?string => '42');

        $this->assertNotSame('42', $identity->id());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $identity->id());
    }

    public function test_persist_queues_cookie_with_configured_name_and_ttl(): void
    {
        $jar = $this->app->make('cookie');
        $identity = $this->identity(fn (): ?string => null, $jar);

        $identity->persist();

        $queued = $jar->queued('simple_cart_id');

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

        $this->assertNull($jar->queued('simple_cart_id'));

        $list->add(new FakeProduct(price: 500));

        $queued = $jar->queued('simple_cart_id');

        $this->assertNotNull($queued);
        $this->assertSame(1, CartRecord::query()->where('owner', $queued->getValue())->count());
    }

    public function test_custom_pattern_accepts_legacy_uniqid_values(): void
    {
        $pattern = '/^([0-9a-f]{32}|cart[0-9a-f]{13}\d\.\d{8})$/';
        $legacy = uniqid('cart', true);

        $this->assertSame($legacy, $this->identity(fn (): ?string => $legacy, pattern: $pattern)->id());
        $this->assertSame('cart5f3a1b2c4d5e64.12345678', $this->identity(fn (): ?string => 'cart5f3a1b2c4d5e64.12345678', pattern: $pattern)->id());
        $this->assertNotSame('cartXYZ.1', $this->identity(fn (): ?string => 'cartXYZ.1', pattern: $pattern)->id());
        $this->assertNotSame('cart5f3a1b2c4d5e6.12345678', $this->identity(fn (): ?string => 'cart5f3a1b2c4d5e6.12345678', pattern: $pattern)->id());
        $this->assertNotSame($legacy, $this->identity(fn (): ?string => $legacy)->id());
    }

    public function test_pattern_comes_from_config(): void
    {
        config()->set('simple_cart.identity.cookie.pattern', '/^([0-9a-f]{32}|cart[0-9a-f]{13}\d\.\d{8})$/');
        $this->app['request']->cookies->set('simple_cart_id', 'cart5f3a1b2c4d5e64.12345678');

        $identity = $this->app->make(\TimurTurdyev\SimpleCart\Identity\IdentityManager::class)->driver('cookie');

        $this->assertSame('cart5f3a1b2c4d5e64.12345678', $identity->id());
    }

    public function test_broken_pattern_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->identity(fn (): ?string => null, pattern: '/[unclosed');
    }

    public function test_pattern_that_rejects_generated_ids_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->identity(fn (): ?string => null, pattern: '/^cart.+$/');
    }

    private function identity(Closure $cookieValue, ?QueueingFactory $jar = null, ?string $pattern = null): CookieIdentity
    {
        return new CookieIdentity(
            cookies: $jar ?? $this->app->make('cookie'),
            cookieValue: $cookieValue,
            name: 'simple_cart_id',
            ttlMinutes: 60 * 24 * 30,
            pattern: $pattern ?? CookieIdentity::DEFAULT_PATTERN,
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
