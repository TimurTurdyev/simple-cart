<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use TimurTurdyev\SimpleCart\Contracts\HasQuantityRule;
use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Exceptions\InvalidConfigurationException;
use TimurTurdyev\SimpleCart\Exceptions\InvalidLineException;
use TimurTurdyev\SimpleCart\Support\ModelCache;
use TimurTurdyev\SimpleCart\Support\Price;
use TimurTurdyev\SimpleCart\Support\QuantityRule;

final readonly class Line
{
    private function __construct(
        public string $id,
        public string|int $purchasableId,
        public ?string $purchasableType,
        public string $name,
        public Price $price,
        public int $quantity,
        public array $options,
        public array $meta = [],
        public ?QuantityRule $quantityRule = null,
        public ?Price $previousPrice = null,
    ) {
        if (trim($this->name) === '') {
            throw InvalidLineException::emptyName();
        }

        if ($this->quantity < 1) {
            throw InvalidLineException::invalidQuantity($this->quantity);
        }
    }

    public static function of(
        string|int $purchasableId,
        string $name,
        Price $price,
        int $quantity = 1,
        array $options = [],
        ?string $purchasableType = null,
        array $meta = [],
        ?array $keyOptions = null,
        ?QuantityRule $quantityRule = null,
    ): self {
        return new self(
            id: self::identity($purchasableId, $options, $purchasableType, $keyOptions),
            purchasableId: $purchasableId,
            purchasableType: $purchasableType,
            name: $name,
            price: $price,
            quantity: $quantity,
            options: $options,
            meta: $meta,
            quantityRule: $quantityRule,
        );
    }

    public static function for(
        Purchasable $item,
        int $quantity = 1,
        array $options = [],
        array $meta = [],
        ?array $keyOptions = null,
    ): self {
        return self::of(
            purchasableId: $item->cartId(),
            name: $item->cartName(),
            price: $item->cartPrice(),
            quantity: $quantity,
            options: $options,
            purchasableType: $item::class,
            meta: $meta,
            keyOptions: $keyOptions,
            quantityRule: $item instanceof HasQuantityRule ? $item->cartQuantityRule() : null,
        );
    }

    /**
     * @param list<string>|null $keyOptions Only these option keys take part in
     *                                      the identity; null means every option.
     */
    public static function identity(
        string|int $purchasableId,
        array $options = [],
        ?string $type = null,
        ?array $keyOptions = null,
    ): string {
        if ($keyOptions !== null) {
            $options = array_intersect_key($options, array_flip($keyOptions));
        }

        $normalized = self::normalize($options);

        return substr(sha1($type.'|'.$purchasableId.'|'.json_encode($normalized)), 0, 16);
    }

    public function withIdentity(?array $keyOptions): self
    {
        return new self(
            self::identity($this->purchasableId, $this->options, $this->purchasableType, $keyOptions),
            $this->purchasableId,
            $this->purchasableType,
            $this->name,
            $this->price,
            $this->quantity,
            $this->options,
            $this->meta,
            $this->quantityRule,
            $this->previousPrice,
        );
    }

    public function withQuantity(int $quantity): self
    {
        return new self(
            $this->id,
            $this->purchasableId,
            $this->purchasableType,
            $this->name,
            $this->price,
            $quantity,
            $this->options,
            $this->meta,
            $this->quantityRule,
            $this->previousPrice,
        );
    }

    /**
     * Sets the current price and remembers the price the customer saw before
     * the first unacknowledged change; returning to that price clears the mark.
     */
    public function withPrice(Price $price): self
    {
        if ($price->equals($this->price)) {
            return $this;
        }

        $seen = $this->previousPrice ?? $this->price;

        return $this->copyWithPrice($price, $price->equals($seen) ? null : $seen);
    }

    public function priceChanged(): bool
    {
        return $this->previousPrice !== null;
    }

    public function acknowledgePrice(): self
    {
        return $this->previousPrice === null ? $this : $this->copyWithPrice($this->price, null);
    }

    public function addQuantity(int $quantity): self
    {
        return $this->withQuantity($this->quantity + $quantity);
    }

    public function subtotal(): Price
    {
        return $this->price->times($this->quantity);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function model(): ?object
    {
        if ($this->purchasableType === null
            || ! class_exists($this->purchasableType)
            || ! method_exists($this->purchasableType, 'query')) {
            return null;
        }

        if (ModelCache::has($this)) {
            return ModelCache::get($this);
        }

        $model = $this->purchasableType::query()->find($this->purchasableId);
        ModelCache::put($this, $model);

        return $model;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'purchasable_id' => $this->purchasableId,
            'purchasable_type' => $this->purchasableType,
            'name' => $this->name,
            'price' => $this->price->minor(),
            'quantity' => $this->quantity,
            'options' => $this->options,
            'meta' => $this->meta,
        ];

        if ($this->quantityRule !== null) {
            $data['quantity_rule'] = $this->quantityRule->toArray();
        }

        if ($this->previousPrice !== null) {
            $data['previous_price'] = $this->previousPrice->minor();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        foreach (['id', 'purchasable_id', 'name', 'price', 'quantity'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw InvalidLineException::missingKey($key);
            }
        }

        $checks = [
            'id' => is_string($data['id']),
            'purchasable_id' => is_string($data['purchasable_id']) || is_int($data['purchasable_id']),
            'purchasable_type' => is_string($data['purchasable_type'] ?? null) || ($data['purchasable_type'] ?? null) === null,
            'name' => is_string($data['name']),
            'price' => is_int($data['price']),
            'quantity' => is_int($data['quantity']),
            'options' => is_array($data['options'] ?? []),
            'meta' => is_array($data['meta'] ?? []),
            'quantity_rule' => is_array($data['quantity_rule'] ?? null) || ($data['quantity_rule'] ?? null) === null,
            'previous_price' => is_int($data['previous_price'] ?? null) || ($data['previous_price'] ?? null) === null,
        ];

        foreach ($checks as $key => $valid) {
            if (! $valid) {
                throw InvalidLineException::invalidValue($key);
            }
        }

        return new self(
            id: $data['id'],
            purchasableId: $data['purchasable_id'],
            purchasableType: $data['purchasable_type'] ?? null,
            name: $data['name'],
            price: Price::fromMinor($data['price']),
            quantity: $data['quantity'],
            options: $data['options'] ?? [],
            meta: $data['meta'] ?? [],
            quantityRule: self::ruleFromArray($data['quantity_rule'] ?? null),
            previousPrice: isset($data['previous_price']) ? Price::fromMinor($data['previous_price']) : null,
        );
    }

    private function copyWithPrice(Price $price, ?Price $previousPrice): self
    {
        return new self(
            $this->id,
            $this->purchasableId,
            $this->purchasableType,
            $this->name,
            $price,
            $this->quantity,
            $this->options,
            $this->meta,
            $this->quantityRule,
            $previousPrice,
        );
    }

    private static function ruleFromArray(?array $data): ?QuantityRule
    {
        if ($data === null) {
            return null;
        }

        try {
            return QuantityRule::fromArray($data);
        } catch (InvalidConfigurationException) {
            throw InvalidLineException::invalidValue('quantity_rule');
        }
    }

    private static function normalize(array $options): array
    {
        ksort($options);

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = self::normalize($value);
            }
        }

        return $options;
    }
}
