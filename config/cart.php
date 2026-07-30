<?php

declare(strict_types=1);

return [

    // Storage driver: session, database or a custom driver registered via extend().
    'storage' => env('CART_STORAGE', 'session'),

    // Named item lists and their policies.
    // policy: append (quantities accumulate) or toggle (add/remove on repeat).
    // limit: optional maximum number of lines.
    'lists' => [
        'cart' => ['policy' => 'append'],
        'wishlist' => ['policy' => 'toggle'],
        'compare' => ['policy' => 'toggle', 'limit' => 4],
    ],

    // Dispatch lifecycle events (LineAdded, LineRemoved, ...).
    'events' => true,

];
