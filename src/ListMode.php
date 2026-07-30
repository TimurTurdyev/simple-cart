<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart;

enum ListMode: string
{
    case Append = 'append';
    case Toggle = 'toggle';
}
