<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

enum ListStatus: string
{
    case Active = 'active';
    case Ordered = 'ordered';
    case Merged = 'merged';
    case Expired = 'expired';
}
