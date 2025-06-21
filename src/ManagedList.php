<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Events\LineAdded;
use TimurTurdyev\SimpleCart\Events\LineRemoved;
use TimurTurdyev\SimpleCart\Events\LineUpdated;
use TimurTurdyev\SimpleCart\Events\ListCleared;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final class ManagedList
{
    private ?Cart $state = null;

    public function __construct(
        private readonly string $name,
        private readonly ListPolicy $policy,
        private readonly Storage $storage,
        private readonly CartManager $manager,
        private readonly ?Dispatcher $events = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function add(Purchasable|Line $item, int $quantity = 1, array $options = [], array $meta = []): Line
    {
        $line = $this->line($item, $quantity, $options, $meta);
        $before = $this->state();
        $state = $before->add($line);

        if ($state->list === $before->list) {
            return $before->get($line->id);
        }

        $this->mutate($state);

        $added = $this->get($line->id);
        $this->dispatch(new LineAdded($this->name, $added));

        return $added;
    }

    public function toggle(Purchasable|Line $item, array $options = []): bool
    {
        $line = $this->line($item, 1, $options);
        $existing = $this->get($line->id);

        if ($existing !== null) {
            $this->mutate($this->state()->remove($existing->id));
            $this->dispatch(new LineRemoved($this->name, $existing));

            return false;
        }

        $this->mutate($this->state()->add($line));
        $this->dispatch(new LineAdded($this->name, $line));

        return true;
    }

    public function remove(string $id): void
    {
        $line = $this->get($id);

        if ($line === null) {
            return;
        }

        $this->mutate($this->state()->remove($id));
        $this->dispatch(new LineRemoved($this->name, $line));
    }

    public function setQuantity(string $id, int $quantity): void
    {
        $before = $this->state()->get($id) ?? throw UnknownLineException::forId($id);

        $this->mutate($this->state()->setQuantity($id, $quantity));

        $after = $this->get($id);

        $this->dispatch(
            $after === null
                ? new LineRemoved($this->name, $before)
                : new LineUpdated($this->name, $after),
        );
    }

    public function changeQuantity(string $id, int $delta): void
    {
        $line = $this->state()->get($id) ?? throw UnknownLineException::forId($id);

        $this->setQuantity($id, $line->quantity + $delta);
    }

    public function has(Purchasable|Line|string $item, array $options = []): bool
    {
        return $this->state()->has($this->idOf($item, $options));
    }

    public function get(string $id): ?Line
    {
        return $this->state()->get($id);
    }

    /**
     * @return Collection<string, Line>
     */
    public function items(): Collection
    {
        return $this->state()->items();
    }

    public function count(): int
    {
        return $this->state()->count();
    }

    public function totalQuantity(): int
    {
        return $this->state()->totalQuantity();
    }

    public function isEmpty(): bool
    {
        return $this->state()->isEmpty();
    }

    public function adjust(Adjuster ...$adjusters): void
    {
        $this->mutate($this->state()->adjust(...$adjusters));
    }

    public function removeAdjuster(string $name): void
    {
        $this->mutate($this->state()->withoutAdjuster($name));
    }

    /**
     * @return array<string, Adjuster>
     */
    public function adjusters(): array
    {
        return $this->state()->adjusters;
    }

    public function subtotal(): Price
    {
        return $this->state()->subtotal();
    }

    public function totals(): Totals
    {
        return $this->state()->totals();
    }

    public function total(): Price
    {
        return $this->state()->total();
    }

    public function clear(): void
    {
        $this->state = $this->state()->clear();
        $this->storage->forget($this->name);
        $this->dispatch(new ListCleared($this->name));
    }

    public function moveTo(string $target, Purchasable|Line|string $item, array $options = []): void
    {
        $id = $this->idOf($item, $options);
        $line = $this->state()->get($id) ?? throw UnknownLineException::forId($id);

        $this->manager->list($target)->add($line);
        $this->remove($id);
    }

    public function moveToCart(Purchasable|Line|string $item, array $options = []): void
    {
        $this->moveTo('cart', $item, $options);
    }

    private function state(): Cart
    {
        return $this->state ??= Cart::fromArray($this->policy, $this->storage->read($this->name));
    }

    private function mutate(Cart $state): void
    {
        $this->state = $state;

        $empty = $state->isEmpty() && $state->adjusters === [];

        $this->storage->write($this->name, $empty ? [] : $state->toArray());
    }

    private function line(Purchasable|Line $item, int $quantity, array $options, array $meta = []): Line
    {
        return $item instanceof Line ? $item : Line::for($item, $quantity, $options, $meta);
    }

    private function idOf(Purchasable|Line|string $item, array $options): string
    {
        return match (true) {
            is_string($item) => $item,
            $item instanceof Line => $item->id,
            default => Line::identity($item->cartId(), $options, $item::class),
        };
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
