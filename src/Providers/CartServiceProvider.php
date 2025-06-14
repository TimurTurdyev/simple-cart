<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimurTurdyev\SimpleCart\CartManager;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Identity\IdentityManager;
use TimurTurdyev\SimpleCart\Listeners\MergeGuestCart;
use TimurTurdyev\SimpleCart\ManagedList;
use TimurTurdyev\SimpleCart\Storage\StorageManager;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cart.php', 'cart');

        $this->app->singleton(IdentityManager::class);

        $this->app->bind(
            CartIdentity::class,
            fn (Application $app): CartIdentity => $app->make(IdentityManager::class)->driver(),
        );

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
    }
}
