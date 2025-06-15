<?php

declare(strict_types=1);

return [

    // Storage driver: session, database or a custom driver registered via extend().
    'storage' => env('CART_STORAGE', 'session'),

    // How the current visitor's cart id is resolved: session (default), cookie
    // or a custom driver registered via IdentityManager::extend().
    // The cookie driver queues the cookie lazily - only when the visitor
    // actually writes to a list for the first time. Cookie encryption is up to
    // the application middleware (EncryptCookies).
    'identity' => [
        'driver' => env('CART_IDENTITY', 'session'),
        'cookie' => [
            'name' => env('SIMPLE_CART_COOKIE', 'simple_cart_id'),
            'ttl_minutes' => 60 * 24 * 30,
        ],
    ],

    // Named item lists and their policies.
    // policy: append (quantities accumulate) or toggle (add/remove on repeat).
    // limit: optional maximum number of lines.
    'lists' => [
        'cart' => ['policy' => 'append'],
        'wishlist' => ['policy' => 'toggle'],
        'compare' => ['policy' => 'toggle', 'limit' => 4],
    ],

    // Database driver settings.
    'database' => [
        'table' => 'simple_cart_lists',
        'connection' => null,
    ],

    // Write-through cache layer wrapped around the active storage driver,
    // including custom drivers registered via extend(). One cache key per
    // cart: <prefix><owner id> holds every list of that visitor.
    // store: cache store name (null - the default store).
    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl_minutes' => 60 * 24 * 30,
        'prefix' => 'simple_cart_',
    ],

    // Merge the guest cart into the user cart on login (database driver only).
    // strategy: sum (quantities add up), keep (user lines win) or replace (guest wins).
    'merge' => [
        'enabled' => true,
        'strategy' => 'sum',
    ],

    // Dispatch lifecycle events (LineAdded, LineRemoved, ...).
    'events' => true,

];
