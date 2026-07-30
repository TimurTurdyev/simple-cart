<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TimurTurdyev\Cart\Providers\CartServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }
}
