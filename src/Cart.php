<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart;

use Illuminate\Support\Collection;
use TimurTurdyev\Cart\Contracts\Adjuster;
use TimurTurdyev\Cart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\Cart\Exceptions\UnknownLineException;
use TimurTurdyev\Cart\Support\Price;
use TimurTurdyev\Cart\Support\Totals;

final readonly class Cart
{
    /**
     * @param array<string, Adjuster> $adjusters
     */
    private function __construct(
        public ItemList $list,
        public array $adjusters,
    ) {
    }

    public static function make(?ListPolicy $policy = null): self
    {
        return new self(ItemList::make($policy ?? new ListPolicy()), []);
    }

    public function add(Line $line): self
    {
        return $this->withList($this->list->add($line));
    }

    public function remove(string $id): self
    {
        return $this->withList($this->list->remove($id));
    }

    public function has(string $id): bool
    {
        return $this->list->has($id);
    }

    public function get(string $id): ?Line
    {
        return $this->list->get($id);
    }

    /**
     * @return Collection<string, Line>
     */
    public function items(): Collection
    {
        return $this->list->items();
    }

    public function count(): int
    {
        return $this->list->count();
    }

    public function totalQuantity(): int
    {
        return $this->list->totalQuantity();
    }

    public function isEmpty(): bool
    {
        return $this->list->isEmpty();
    }

    public function setQuantity(string $id, int $quantity): self
    {
        $line = $this->list->get($id) ?? throw UnknownLineException::forId($id);

        if ($quantity < 1) {
            return $this->remove($id);
        }

        return $this->withList($this->list->remove($id)->add($line->withQuantity($quantity)));
    }

    public function changeQuantity(string $id, int $delta): self
    {
        $line = $this->list->get($id) ?? throw UnknownLineException::forId($id);

        return $this->setQuantity($id, $line->quantity + $delta);
    }

    public function adjust(Adjuster ...$adjusters): self
    {
        $merged = $this->adjusters;

        foreach ($adjusters as $adjuster) {
            unset($merged[$adjuster->name()]);
            $merged[$adjuster->name()] = $adjuster;
        }

        return new self($this->list, $merged);
    }

    public function withoutAdjuster(string $name): self
    {
        $adjusters = $this->adjusters;
        unset($adjusters[$name]);

        return new self($this->list, $adjusters);
    }

    public function subtotal(): Price
    {
        return $this->list->subtotal();
    }

    public function totals(): Totals
    {
        return array_reduce(
            $this->adjusters,
            fn (Totals $totals, Adjuster $adjuster): Totals => $adjuster->adjust($totals),
            Totals::of($this->subtotal()),
        );
    }

    public function total(): Price
    {
        return $this->totals()->total();
    }

    public function merge(self $guest, MergeStrategy $strategy): self
    {
        if ($strategy === MergeStrategy::Replace) {
            return $guest;
        }

        $merged = $this;

        foreach ($guest->list->lines as $line) {
            if ($strategy === MergeStrategy::Keep && $merged->has($line->id)) {
                continue;
            }

            $merged = $merged->add($line);
        }

        foreach ($guest->adjusters as $name => $adjuster) {
            if (! isset($merged->adjusters[$name])) {
                $merged = $merged->adjust($adjuster);
            }
        }

        return $merged;
    }

    public function clear(): self
    {
        return new self($this->list->clear(), []);
    }

    public function toArray(): array
    {
        return [
            'lines' => $this->list->toArray(),
            'adjusters' => array_values(array_map(
                fn (Adjuster $adjuster): array => ['class' => $adjuster::class, 'data' => $adjuster->toArray()],
                $this->adjusters,
            )),
        ];
    }

    public static function fromArray(ListPolicy $policy, array $data): self
    {
        $adjusters = [];

        foreach ($data['adjusters'] ?? [] as $entry) {
            $class = $entry['class'] ?? '';

            if (! class_exists($class) || ! is_subclass_of($class, Adjuster::class)) {
                throw InvalidAdjusterException::invalidClass($class);
            }

            $adjuster = $class::fromArray($entry['data'] ?? []);
            $adjusters[$adjuster->name()] = $adjuster;
        }

        return new self(ItemList::fromArray($policy, $data['lines'] ?? []), $adjusters);
    }

    private function withList(ItemList $list): self
    {
        return new self($list, $this->adjusters);
    }
}
