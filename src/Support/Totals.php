<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Support;

final readonly class Totals
{
    /**
     * @param array<string, Price> $adjustments
     */
    private function __construct(
        public Price $subtotal,
        public array $adjustments,
    ) {
    }

    public static function of(Price $subtotal): self
    {
        return new self($subtotal, []);
    }

    public function addDiscount(string $name, Price $amount): self
    {
        return $this->with($name, $amount->negate());
    }

    public function addFee(string $name, Price $amount): self
    {
        return $this->with($name, $amount);
    }

    public function adjustment(string $name): ?Price
    {
        return $this->adjustments[$name] ?? null;
    }

    public function total(): Price
    {
        $total = array_reduce(
            $this->adjustments,
            fn (Price $carry, Price $amount): Price => $carry->plus($amount),
            $this->subtotal,
        );

        return $total->minor() < 0 ? Price::zero() : $total;
    }

    /**
     * @return array{subtotal: Price, adjustments: array<string, Price>, total: Price}
     */
    public function breakdown(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'adjustments' => $this->adjustments,
            'total' => $this->total(),
        ];
    }

    private function with(string $name, Price $amount): self
    {
        return new self($this->subtotal, [...$this->adjustments, $name => $amount]);
    }
}
