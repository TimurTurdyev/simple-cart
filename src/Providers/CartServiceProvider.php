<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimurTurdyev\Cart\CartManager;
use TimurTurdyev\Cart\Console\ImportLegacyCommand;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Listeners\MergeGuestCart;
use TimurTurdyev\Cart\ManagedList;
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

        $this->app->singleton(
            CartManager::class,
            fn (Application $app): CartManager => new CartManager(
                storage: $app->make(Storage::class),
                config: $app->make('config'),
                events: $app->make('config')->get('cart.events') ? $app->make('events') : null,
            ),
        );

        $this->app->bind('cart', fn (Application $app): ManagedList => $app->make(CartManager::class)->list('cart'));
        $this->app->bind('cart.wishlist', fn (Application $app): ManagedList => $app->make(CartManager::class)->list('wishlist'));
        $this->app->bind('cart.compare', fn (Application $app): ManagedList => $app->make(CartManager::class)->list('compare'));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/cart.php' => config_path('cart.php'),
        ], 'cart-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'cart-migrations');

        if ($this->app->make('config')->get('cart.merge.enabled')) {
            $this->app->make('events')->listen(Login::class, MergeGuestCart::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ImportLegacyCommand::class]);
        }
    }
}
