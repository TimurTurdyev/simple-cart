<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart;

use TimurTurdyev\Cart\Contracts\Purchasable;
use TimurTurdyev\Cart\Exceptions\InvalidLineException;
use TimurTurdyev\Cart\Support\Price;

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
    ): self {
        return new self(
            id: self::identity($purchasableId, $options, $purchasableType),
            purchasableId: $purchasableId,
            purchasableType: $purchasableType,
            name: $name,
            price: $price,
            quantity: $quantity,
            options: $options,
        );
    }

    public static function for(Purchasable $item, int $quantity = 1, array $options = []): self
    {
        return self::of(
            purchasableId: $item->cartId(),
            name: $item->cartName(),
            price: $item->cartPrice(),
            quantity: $quantity,
            options: $options,
            purchasableType: $item::class,
        );
    }

    public static function identity(string|int $purchasableId, array $options = [], ?string $type = null): string
    {
        $normalized = self::normalize($options);

        return substr(sha1($type.'|'.$purchasableId.'|'.json_encode($normalized)), 0, 16);
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
        );
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'purchasable_id' => $this->purchasableId,
            'purchasable_type' => $this->purchasableType,
            'name' => $this->name,
            'price' => $this->price->minor(),
            'quantity' => $this->quantity,
            'options' => $this->options,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            purchasableId: $data['purchasable_id'],
            purchasableType: $data['purchasable_type'] ?? null,
            name: $data['name'],
            price: Price::fromMinor($data['price']),
            quantity: $data['quantity'],
            options: $data['options'] ?? [],
        );
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
