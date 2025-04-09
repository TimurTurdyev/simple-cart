<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Legacy;

use TimurTurdyev\Cart\Adjusters\FixedDiscount;
use TimurTurdyev\Cart\Adjusters\PercentageDiscount;
use TimurTurdyev\Cart\Adjusters\PercentageFee;
use TimurTurdyev\Cart\Adjusters\Shipping;
use TimurTurdyev\Cart\Contracts\Adjuster;
use TimurTurdyev\Cart\Line;
use TimurTurdyev\Cart\Support\Price;

final readonly class LegacyImporter
{
    /**
     * @param array{items?: array, conditions?: array} $legacy
     */
    public function payload(array $legacy): array
    {
        return [
            'lines' => $this->lines($legacy['items'] ?? []),
            'adjusters' => $this->adjusters($legacy['conditions'] ?? []),
        ];
    }

    public function lines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            if (! isset($item['id'], $item['name'], $item['price'], $item['quantity'])) {
                continue;
            }

            $lines[] = Line::of(
                purchasableId: $item['id'],
                name: (string) $item['name'],
                price: is_int($item['price']) ? Price::fromMinor($item['price'] * 100) : Price::fromDecimal($item['price']),
                quantity: (int) $item['quantity'],
                options: (array) ($item['attributes'] ?? []),
                purchasableType: $item['associatedModel'] ?? null,
            )->toArray();
        }

        return $lines;
    }

    public function adjusters(array $conditions): array
    {
        $adjusters = [];

        foreach ($conditions as $condition) {
            $adjuster = $this->adjuster($condition);

            if ($adjuster !== null) {
                $adjusters[] = ['class' => $adjuster::class, 'data' => $adjuster->toArray()];
            }
        }

        return $adjusters;
    }

    private function adjuster(mixed $condition): ?Adjuster
    {
        if (! is_array($condition) || ! isset($condition['name'], $condition['value'])) {
            return null;
        }

        $name = (string) $condition['name'];
        $value = (string) $condition['value'];

        if (preg_match('/^([+-]?)(\d+(?:\.\d+)?)(%?)$/', $value, $matches) !== 1) {
            return null;
        }

        $negative = $matches[1] === '-';
        $number = (float) $matches[2];

        if ($matches[3] === '%') {
            return $negative
                ? new PercentageDiscount($name, $number)
                : new PercentageFee($name, $number);
        }

        return $negative
            ? new FixedDiscount($name, Price::fromDecimal($matches[2]))
            : new Shipping(Price::fromDecimal($matches[2]), $name);
    }
}
