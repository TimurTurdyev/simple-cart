<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use TimurTurdyev\Cart\Contracts\Storage;
use TimurTurdyev\Cart\Exceptions\UnknownListException;

final class CartManager
{
    /**
     * @var array<string, ManagedList>
     */
    private array $lists = [];

    public function __construct(
        private readonly Storage $storage,
        private readonly Repository $config,
        private readonly ?Dispatcher $events = null,
    ) {
    }

    public function list(string $name = 'cart'): ManagedList
    {
        return $this->lists[$name] ??= $this->build($name);
    }

    private function build(string $name): ManagedList
    {
        $config = $this->config->get("cart.lists.{$name}");

        if (! is_array($config)) {
            throw UnknownListException::forName($name);
        }

        return new ManagedList(
            name: $name,
            policy: ListPolicy::fromConfig($config),
            storage: $this->storage,
            manager: $this,
            events: $this->events,
        );
    }
}
