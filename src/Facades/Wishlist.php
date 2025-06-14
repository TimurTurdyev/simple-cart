<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Facades;

use Illuminate\Support\Facades\Facade;
use TimurTurdyev\SimpleCart\ManagedList;

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
