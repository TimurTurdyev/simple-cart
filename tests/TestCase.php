<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TimurTurdyev\SimpleCart\Providers\CartServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }
}
