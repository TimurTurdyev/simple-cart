<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Contracts\Storage;
use TimurTurdyev\SimpleCart\Contracts\SupportsAtomicUpdate;
use TimurTurdyev\SimpleCart\Contracts\SupportsLifecycle;
use TimurTurdyev\SimpleCart\Events\LineAdded;
use TimurTurdyev\SimpleCart\Events\LineRemoved;
use TimurTurdyev\SimpleCart\Events\LineRepriced;
use TimurTurdyev\SimpleCart\Events\LineUpdated;
use TimurTurdyev\SimpleCart\Events\ListAttributesUpdated;
use TimurTurdyev\SimpleCart\Events\ListCheckedOut;
use TimurTurdyev\SimpleCart\Events\ListCleared;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\Support\ModelCache;
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

    /**
     * Without an explicit quantity a new line gets the minimum of its quantity
     * rule and an existing line grows by one step (1 when there is no rule).
     * A Line instance always keeps its own quantity.
     */
    public function add(Purchasable|Line $item, ?int $quantity = null, array $options = [], array $meta = []): Line
    {
        $line = $this->line($item, $quantity ?? 1, $options, $meta);
        $byRule = $quantity === null && ! $item instanceof Line;
        $added = false;

        $state = $this->apply(function (Cart $cart) use ($line, $byRule, &$added): Cart {
            $next = $cart->add($byRule ? $this->ruleQuantity($cart, $line) : $line);
            $added = $next->list !== $cart->list;

            return $next;
        });

        $stored = $state->get($line->id);

        if ($added) {
            $this->dispatch(new LineAdded($this->name, $stored));
        }

        return $stored;
    }

    public function toggle(Purchasable|Line $item, array $options = []): bool
    {
        $line = $this->line($item, 1, $options);
        $removed = null;

        $state = $this->apply(function (Cart $cart) use ($line, &$removed): Cart {
            $removed = $cart->get($line->id);

            return $removed === null ? $cart->add($line) : $cart->remove($line->id);
        });

        if ($removed !== null) {
            $this->dispatch(new LineRemoved($this->name, $removed));

            return false;
        }

        $this->dispatch(new LineAdded($this->name, $state->get($line->id)));

        return true;
    }

    public function remove(string $id): void
    {
        $removed = null;

        $this->apply(function (Cart $cart) use ($id, &$removed): Cart {
            $removed = $cart->get($id);

            return $cart->remove($id);
        });

        if ($removed !== null) {
            $this->dispatch(new LineRemoved($this->name, $removed));
        }
    }

    public function setQuantity(string $id, int $quantity): void
    {
        $this->updateQuantity($id, fn (Line $line): int => $quantity);
    }

    public function changeQuantity(string $id, int $delta): void
    {
        $this->updateQuantity($id, fn (Line $line): int => $line->quantity + $delta);
    }

    /**
     * Moves the quantity by whole steps of the line's quantity rule (by 1 when
     * there is no rule), as the "+" and "-" buttons do. Going below the rule
     * minimum removes the line.
     */
    public function stepQuantity(string $id, int $steps = 1): void
    {
        $this->updateQuantity($id, function (Line $line) use ($steps): int {
            $rule = $this->policy->quantityRuleFor($line);
            $quantity = $line->quantity + $steps * ($rule?->step ?? 1);

            return $rule !== null && $quantity < $rule->min ? 0 : $quantity;
        });
    }

    /**
     * Re-prices lines in place: the resolver returns the current price of a
     * line or null to leave it as is. Without a resolver the price is taken
     * from the hydrated Purchasable models. Returns the number of changed lines.
     *
     * @param (Closure(Line): ?Price)|null $resolver
     */
    public function reprice(?Closure $resolver = null): int
    {
        $resolver ??= $this->modelPrices();
        $prices = [];

        foreach ($this->state()->items() as $id => $line) {
            $price = $resolver($line);

            if ($price !== null) {
                $prices[$id] = $price;
            }
        }

        return $this->applyPrices($prices);
    }

    public function repriceLine(string $id, Price $price): void
    {
        $this->state()->get($id) ?? throw UnknownLineException::forId($id);

        $this->applyPrices([$id => $price]);
    }

    /**
     * Clears the "price changed" marks once the customer has seen them.
     */
    public function acknowledgePrices(): void
    {
        $this->apply(fn (Cart $cart): Cart => $cart->acknowledgePrices());
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

    /**
     * @return Collection<string, object>
     */
    public function models(): Collection
    {
        $byType = [];

        foreach ($this->state()->items() as $line) {
            $type = $line->purchasableType;

            if ($type === null || ! class_exists($type) || ! method_exists($type, 'query')) {
                continue;
            }

            $byType[$type][] = $line;
        }

        $models = [];

        foreach ($byType as $type => $lines) {
            $ids = array_values(array_unique(array_map(
                fn (Line $line): string|int => $line->purchasableId,
                $lines,
            )));

            $found = [];

            foreach ($type::query()->findMany($ids) as $model) {
                $key = method_exists($model, 'getKey') ? $model->getKey() : $model->id;
                $found[(string) $key] = $model;
            }

            foreach ($lines as $line) {
                $model = $found[(string) $line->purchasableId] ?? null;
                ModelCache::put($line, $model);

                if ($model !== null) {
                    $models[$line->id] = $model;
                }
            }
        }

        return new Collection($models);
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
        $this->apply(fn (Cart $cart): Cart => $cart->adjust(...$adjusters));
    }

    public function removeAdjuster(string $name): void
    {
        $this->apply(fn (Cart $cart): Cart => $cart->withoutAdjuster($name));
    }

    /**
     * @return array<string, Adjuster>
     */
    public function adjusters(): array
    {
        return $this->state()->adjusters;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->state()->attributes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->state()->attributes;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->setAttributes([$key => $value]);
    }

    /**
     * Merges the values into the list attributes: contact, region, source
     * tags, a form draft - anything the list should carry along.
     *
     * @param array<string, mixed> $values
     */
    public function setAttributes(array $values): void
    {
        $changed = [];

        $this->apply(function (Cart $cart) use ($values, &$changed): Cart {
            $changed = array_keys(array_filter(
                $values,
                fn (mixed $value, string|int $key): bool => ! array_key_exists($key, $cart->attributes) || $cart->attributes[$key] !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            return $cart->withAttributes($values);
        });

        $this->dispatchAttributes($changed);
    }

    public function forgetAttribute(string $key): void
    {
        $changed = [];

        $this->apply(function (Cart $cart) use ($key, &$changed): Cart {
            $changed = array_key_exists($key, $cart->attributes) ? [$key] : [];

            return $cart->withoutAttribute($key);
        });

        $this->dispatchAttributes($changed);
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

    /**
     * Ends the list as ordered: the record is kept with the "ordered" status
     * and the reference (an order number or id of the application), and the
     * next write starts a new list. Returns the snapshot of exactly the record
     * that was closed, so lines added by another tab a moment ago are not lost.
     */
    public function checkout(?string $reference = null): Cart
    {
        if ($this->storage instanceof SupportsLifecycle) {
            $payload = $this->storage->close($this->name, ListStatus::Ordered, $reference);
        } else {
            $payload = $this->storage->read($this->name);
            $this->storage->forget($this->name);
        }

        $snapshot = Cart::fromArray($this->policy, $payload);
        $this->state = Cart::make($this->policy);

        if ($payload !== []) {
            $this->dispatch(new ListCheckedOut($this->name, $reference, $snapshot));
        }

        return $snapshot;
    }

    public function moveTo(string $target, Purchasable|Line|string $item, array $options = []): void
    {
        $id = $this->idOf($item, $options);
        $line = $this->state()->get($id) ?? throw UnknownLineException::forId($id);

        $this->manager->list($target)->receive($line);
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

    /**
     * Runs the operation against the freshest stored state. With an atomic
     * storage the operation may run more than once, so it must decide
     * everything from the cart it receives and keep side effects out.
     *
     * @param Closure(Cart): Cart $operation
     */
    private function apply(Closure $operation): Cart
    {
        if (! $this->storage instanceof SupportsAtomicUpdate) {
            $state = $operation($this->state());
            $this->storage->write($this->name, $this->payload($state));

            return $this->state = $state;
        }

        $state = null;

        $this->storage->update($this->name, function (array $payload) use ($operation, &$state): array {
            $state = $operation(Cart::fromArray($this->policy, $payload));

            return $this->payload($state);
        });

        return $this->state = $state;
    }

    /**
     * Takes a line moved from another list: the line is re-keyed for this list
     * and its quantity is fitted into this list's quantity rule.
     */
    private function receive(Line $line): void
    {
        $moved = $line->withIdentity($this->policy->keyOptions);
        $added = false;

        $state = $this->apply(function (Cart $cart) use ($moved, &$added): Cart {
            $next = $cart->mergeLine($moved);
            $added = $next->list !== $cart->list;

            return $next;
        });

        if ($added) {
            $this->dispatch(new LineAdded($this->name, $state->get($moved->id)));
        }
    }

    /**
     * @param array<string, Price> $prices
     */
    private function applyPrices(array $prices): int
    {
        $changed = [];

        $state = $this->apply(function (Cart $cart) use ($prices, &$changed): Cart {
            $changed = [];

            foreach ($prices as $id => $price) {
                $line = $cart->get($id);

                if ($line === null || $line->price->equals($price)) {
                    continue;
                }

                $changed[$id] = $line->price;
                $cart = $cart->reprice($id, $price);
            }

            return $cart;
        });

        foreach ($changed as $id => $previous) {
            $this->dispatch(new LineRepriced($this->name, $state->get($id), $previous));
        }

        return count($changed);
    }

    /**
     * @return Closure(Line): ?Price
     */
    private function modelPrices(): Closure
    {
        $models = $this->models();

        return function (Line $line) use ($models): ?Price {
            $model = $models->get($line->id);

            return $model instanceof Purchasable ? $model->cartPrice() : null;
        };
    }

    private function ruleQuantity(Cart $cart, Line $line): Line
    {
        $rule = $this->policy->quantityRuleFor($line);

        if ($rule === null) {
            return $line;
        }

        return $line->withQuantity($cart->has($line->id) ? $rule->step : $rule->min);
    }

    /**
     * @param Closure(Line): int $quantity
     */
    private function updateQuantity(string $id, Closure $quantity): void
    {
        $before = null;

        $state = $this->apply(function (Cart $cart) use ($id, $quantity, &$before): Cart {
            $before = $cart->get($id) ?? throw UnknownLineException::forId($id);

            return $cart->setQuantity($id, $quantity($before));
        });

        $after = $state->get($id);

        $this->dispatch(
            $after === null
                ? new LineRemoved($this->name, $before)
                : new LineUpdated($this->name, $after),
        );
    }

    private function payload(Cart $state): array
    {
        return $state->isBlank() ? [] : $state->toArray();
    }

    /**
     * @param list<string> $changed
     */
    private function dispatchAttributes(array $changed): void
    {
        if ($changed !== []) {
            $this->dispatch(new ListAttributesUpdated($this->name, $changed));
        }
    }

    private function line(Purchasable|Line $item, int $quantity, array $options, array $meta = []): Line
    {
        return $item instanceof Line
            ? $item->withIdentity($this->policy->keyOptions)
            : Line::for($item, $quantity, $options, $meta, $this->policy->keyOptions);
    }

    private function idOf(Purchasable|Line|string $item, array $options): string
    {
        return match (true) {
            is_string($item) => $item,
            $item instanceof Line => $item->id,
            default => Line::identity($item->cartId(), $options, $item::class, $this->policy->keyOptions),
        };
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
