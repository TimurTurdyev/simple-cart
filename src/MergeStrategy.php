<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

enum MergeStrategy: string
{
    case Sum = 'sum';
    case Keep = 'keep';
    case Replace = 'replace';
}
