<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use Illuminate\Support\Manager;
use TimurTurdyev\Cart\Contracts\Storage;

final class StorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('cart.storage', 'session');
    }

    protected function createSessionDriver(): Storage
    {
        return new SessionStorage($this->container->make('session.store'));
    }
}
