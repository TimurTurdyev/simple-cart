<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Providers;

use Illuminate\Support\ServiceProvider;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cart.php', 'cart');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/cart.php' => config_path('cart.php'),
        ], 'cart-config');
    }
}
