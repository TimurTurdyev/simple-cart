<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use Illuminate\Support\Collection;
use TimurTurdyev\SimpleCart\Exceptions\InvalidLineException;
use TimurTurdyev\SimpleCart\Exceptions\ListLimitException;
use TimurTurdyev\SimpleCart\Exceptions\QuantityRuleException;
use TimurTurdyev\SimpleCart\Support\Price;

final readonly class ItemList
{
    /**
     * @param array<string, Line> $lines
     */
    private function __construct(
        public ListPolicy $policy,
        public array $lines,
    ) {
    }

    public static function make(ListPolicy $policy, array $lines = []): self
    {
        $keyed = [];

        foreach ($lines as $line) {
            $keyed[$line->id] = $line;
        }

        return new self($policy, $keyed);
    }

    public function add(Line $line): self
    {
        $existing = $this->lines[$line->id] ?? null;

        if ($existing !== null) {
            if ($this->policy->mode === ListMode::Toggle) {
                return $this;
            }

            return $this->put($this->checked($existing->addQuantity($line->quantity)));
        }

        $this->assertLimit();

        return $this->put($this->checked($line));
    }

    /**
     * Like add(), but fits the resulting quantity into the quantity rule
     * instead of failing: used when lists are merged or lines are moved, where
     * the sum of two valid quantities may fall off the step grid.
     */
    public function mergeLine(Line $line): self
    {
        $existing = $this->lines[$line->id] ?? null;

        if ($existing !== null) {
            if ($this->policy->mode === ListMode::Toggle) {
                return $this;
            }

            return $this->put($this->fitted($existing->addQuantity($line->quantity)));
        }

        $this->assertLimit();

        return $this->put($this->fitted($line));
    }

    public function toggle(Line $line): self
    {
        if (isset($this->lines[$line->id])) {
            return $this->remove($line->id);
        }

        $this->assertLimit();

        return $this->put($line);
    }

    public function replace(Line $line): self
    {
        return $this->put($this->checked($line));
    }

    public function reprice(string $id, Price $price): self
    {
        $line = $this->lines[$id] ?? null;

        return $line === null ? $this : $this->put($line->withPrice($price));
    }

    public function acknowledgePrices(): self
    {
        return new self($this->policy, array_map(fn (Line $line): Line => $line->acknowledgePrice(), $this->lines));
    }

    public function remove(string $id): self
    {
        if (! isset($this->lines[$id])) {
            return $this;
        }

        $lines = $this->lines;
        unset($lines[$id]);

        return new self($this->policy, $lines);
    }

    public function has(string $id): bool
    {
        return isset($this->lines[$id]);
    }

    public function get(string $id): ?Line
    {
        return $this->lines[$id] ?? null;
    }

    /**
     * @return Collection<string, Line>
     */
    public function items(): Collection
    {
        return new Collection($this->lines);
    }

    public function count(): int
    {
        return count($this->lines);
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (Line $line): int => $line->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function clear(): self
    {
        return new self($this->policy, []);
    }

    public function subtotal(): Price
    {
        return array_reduce(
            $this->lines,
            fn (Price $carry, Line $line): Price => $carry->plus($line->subtotal()),
            Price::zero(),
        );
    }

    public function toArray(): array
    {
        return array_values(array_map(fn (Line $line): array => $line->toArray(), $this->lines));
    }

    public static function fromArray(ListPolicy $policy, array $data): self
    {
        return self::make($policy, array_map(
            fn (mixed $entry): Line => is_array($entry)
                ? Line::fromArray($entry)
                : throw InvalidLineException::invalidPayload(),
            $data,
        ));
    }

    private function put(Line $line): self
    {
        return new self($this->policy, [...$this->lines, $line->id => $line]);
    }

    private function checked(Line $line): Line
    {
        $rule = $this->policy->quantityRuleFor($line);

        if ($rule !== null && ! $rule->isValid($line->quantity)) {
            throw QuantityRuleException::violated($rule, $line->quantity);
        }

        return $line;
    }

    private function fitted(Line $line): Line
    {
        $rule = $this->policy->quantityRuleFor($line);

        if ($rule === null || $rule->isValid($line->quantity)) {
            return $line;
        }

        return $line->withQuantity($rule->normalize($line->quantity));
    }

    private function assertLimit(): void
    {
        if ($this->policy->limit !== null && count($this->lines) >= $this->policy->limit) {
            throw ListLimitException::reached($this->policy->limit);
        }
    }
}
