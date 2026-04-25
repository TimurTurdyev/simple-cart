<?php

declare(strict_types=1);

return [

    // Storage driver: database (default, run php artisan migrate), session or
    // a custom driver registered via extend(). Session carts live only as long
    // as the session does.
    'storage' => env('CART_STORAGE', 'database'),

    // How the current visitor's cart id is resolved: cookie (default), session
    // or a custom driver registered via IdentityManager::extend().
    // The cookie driver queues the cookie lazily - only when the visitor
    // actually writes to a list for the first time. Cookie encryption is up to
    // the application middleware (EncryptCookies).
    'identity' => [
        'driver' => env('CART_IDENTITY', 'cookie'),
        'cookie' => [
            'name' => env('SIMPLE_CART_COOKIE', 'simple_cart_id'),
            'ttl_minutes' => 60 * 24 * 30,
            // Accepted cookie values. New ids are always 32 hex chars, so the
            // pattern must accept those; widen it to keep carts with older
            // ids, e.g. uniqid('cart', true):
            // '/^([0-9a-f]{32}|cart[0-9a-f]{13}\d\.\d{8})$/'
            'pattern' => '/^[0-9a-f]{32}$/',
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
    // retries: how many times a write is re-applied to the fresh list when
    // another request changed it in between (optimistic locking by version).
    'database' => [
        'table' => 'simple_cart_lists',
        'connection' => null,
        'retries' => 3,
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

    // php artisan simple-cart:prune (database storage only), e.g. daily from
    // the scheduler. expire_after_days: active lists untouched for that long
    // become "expired" - user carts included, the owner column does not tell
    // guests from users; null disables expiry. delete_after_days: closed lists
    // are deleted that long after their status change; null keeps them.
    'prune' => [
        'expire_after_days' => 30,
        'delete_after_days' => [
            'expired' => 30,
            'merged' => 7,
            'ordered' => null,
        ],
    ],

    // Dispatch lifecycle events (LineAdded, LineRemoved, ...).
    'events' => true,

];
