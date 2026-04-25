<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use Illuminate\Support\Collection;
use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAdjusterException;
use TimurTurdyev\SimpleCart\Exceptions\InvalidAttributeException;
use TimurTurdyev\SimpleCart\Exceptions\InvalidLineException;
use TimurTurdyev\SimpleCart\Exceptions\ListLimitException;
use TimurTurdyev\SimpleCart\Exceptions\UnknownLineException;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class Cart
{
    /**
     * @param array<string, Adjuster> $adjusters
     * @param array<string, mixed> $attributes
     */
    private function __construct(
        public ItemList $list,
        public array $adjusters,
        public array $attributes = [],
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

    public function mergeLine(Line $line): self
    {
        return $this->withList($this->list->mergeLine($line));
    }

    public function reprice(string $id, Price $price): self
    {
        return $this->withList($this->list->reprice($id, $price));
    }

    public function acknowledgePrices(): self
    {
        return $this->withList($this->list->acknowledgePrices());
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

        return $this->withList($this->list->replace($line->withQuantity($quantity)));
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

        return new self($this->list, $merged, $this->attributes);
    }

    public function withoutAdjuster(string $name): self
    {
        $adjusters = $this->adjusters;
        unset($adjusters[$name]);

        return new self($this->list, $adjusters, $this->attributes);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function withAttributes(array $values): self
    {
        foreach ($values as $key => $value) {
            self::assertAttribute($key, $value);
        }

        return new self($this->list, $this->adjusters, [...$this->attributes, ...$values]);
    }

    public function withoutAttribute(string $key): self
    {
        $attributes = $this->attributes;
        unset($attributes[$key]);

        return new self($this->list, $this->adjusters, $attributes);
    }

    public function isBlank(): bool
    {
        return $this->isEmpty() && $this->adjusters === [] && $this->attributes === [];
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

            try {
                $merged = $merged->mergeLine($line);
            } catch (ListLimitException) {
                continue;
            }
        }

        foreach ($guest->adjusters as $name => $adjuster) {
            if (! isset($merged->adjusters[$name])) {
                $merged = $merged->adjust($adjuster);
            }
        }

        return new self($merged->list, $merged->adjusters, [...$guest->attributes, ...$merged->attributes]);
    }

    public function clear(): self
    {
        return new self($this->list->clear(), []);
    }

    public function toArray(): array
    {
        $data = [
            'lines' => $this->list->toArray(),
            'adjusters' => array_values(array_map(
                fn (Adjuster $adjuster): array => ['class' => $adjuster::class, 'data' => $adjuster->toArray()],
                $this->adjusters,
            )),
        ];

        if ($this->attributes !== []) {
            $data['attributes'] = $this->attributes;
        }

        return $data;
    }

    public static function fromArray(ListPolicy $policy, array $data): self
    {
        $lines = $data['lines'] ?? [];

        if (! is_array($lines)) {
            throw InvalidLineException::invalidPayload();
        }

        $entries = $data['adjusters'] ?? [];

        if (! is_array($entries)) {
            throw InvalidAdjusterException::malformedEntry();
        }

        $adjusters = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw InvalidAdjusterException::malformedEntry();
            }

            $class = $entry['class'] ?? '';

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Adjuster::class)) {
                throw InvalidAdjusterException::invalidClass(is_string($class) ? $class : gettype($class));
            }

            $payload = $entry['data'] ?? [];

            if (! is_array($payload)) {
                throw InvalidAdjusterException::malformedEntry();
            }

            $adjuster = $class::fromArray($payload);
            $adjusters[$adjuster->name()] = $adjuster;
        }

        $attributes = $data['attributes'] ?? [];

        if (! is_array($attributes)) {
            throw InvalidAttributeException::malformed();
        }

        foreach ($attributes as $key => $value) {
            self::assertAttribute($key, $value);
        }

        return new self(ItemList::fromArray($policy, $lines), $adjusters, $attributes);
    }

    private static function assertAttribute(mixed $key, mixed $value): void
    {
        if (! is_string($key) || $key === '') {
            throw InvalidAttributeException::invalidKey();
        }

        if (! self::isPlain($value)) {
            throw InvalidAttributeException::invalidValue($key);
        }
    }

    private static function isPlain(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! self::isPlain($item)) {
                return false;
            }
        }

        return true;
    }

    private function withList(ItemList $list): self
    {
        return new self($list, $this->adjusters, $this->attributes);
    }
}
