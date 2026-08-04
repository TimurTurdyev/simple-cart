<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

enum ListMode: string
{
    case Append = 'append';
    case Toggle = 'toggle';
}
