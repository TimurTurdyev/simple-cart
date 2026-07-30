<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Storage\StorageManager;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cart.php', 'cart');

        $this->app->singleton(StorageManager::class);

        $this->app->bind(
            Storage::class,
            fn (Application $app): Storage => $app->make(StorageManager::class)->driver(),
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/cart.php' => config_path('cart.php'),
        ], 'cart-config');
    }
}
