<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Facades;

use Illuminate\Support\Facades\Facade;
use TimurTurdyev\Cart\ManagedList;

/**
 * @mixin ManagedList
 */
final class Wishlist extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cart.wishlist';
    }
}
