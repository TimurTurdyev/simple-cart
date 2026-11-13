<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Http;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use PHPUnit\Framework\Attributes\TestWith;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Http\Middleware\IssueCartCookie;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\Fixtures\FakeProduct;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class IssueCartCookieTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware([AddQueuedCookiesToResponse::class, IssueCartCookie::class])->group(function ($router): void {
            $router->get('/page', fn (): string => 'page');
            $router->get('/add', function (): string {
                app(CartManager::class)->list('cart')->add(new FakeProduct());

                return 'added';
            });
            $router->post('/add', fn (): string => 'posted');
        });
    }

    /**
     * The empty value stands in for the null EncryptCookies leaves on a failed decrypt.
     */
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['forged'])]
    public function test_page_without_valid_cookie_gets_one(?string $cookie): void
    {
        if ($cookie !== null) {
            $this->withUnencryptedCookie('simple_cart_id', $cookie);
        }

        $response = $this->get('/page');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $response->getCookie('simple_cart_id', decrypt: false)->getValue());
        $this->assertSame(0, CartRecord::query()->count());
    }

    public function test_write_on_first_page_uses_issued_cookie(): void
    {
        $response = $this->get('/add');

        $this->assertSame($response->getCookie('simple_cart_id', decrypt: false)->getValue(), CartRecord::query()->value('owner'));
        $this->assertCount(1, $response->headers->getCookies());
    }

    public function test_valid_cookie_is_not_reissued(): void
    {
        $this->withUnencryptedCookie('simple_cart_id', str_repeat('ab', 16))
            ->get('/page')
            ->assertCookieMissing('simple_cart_id');
    }

    public function test_cookie_stays_lazy_for_other_methods_and_drivers(): void
    {
        $this->post('/add')->assertCookieMissing('simple_cart_id');

        config()->set('simple_cart.identity.driver', 'session');

        $this->get('/page')->assertCookieMissing('simple_cart_id');
    }
}
