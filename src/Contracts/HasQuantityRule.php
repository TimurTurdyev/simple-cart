<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

use TimurTurdyev\SimpleCart\Support\QuantityRule;

interface HasQuantityRule
{
    public function cartQuantityRule(): QuantityRule;
}
