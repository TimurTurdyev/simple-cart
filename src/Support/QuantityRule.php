<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Support;

use TimurTurdyev\SimpleCart\Exceptions\InvalidConfigurationException;

final readonly class QuantityRule
{
    public function __construct(
        public int $min = 1,
        public int $step = 1,
        public ?int $max = null,
    ) {
        if ($this->min < 1) {
            throw InvalidConfigurationException::invalidQuantityRule("min must be at least 1, [{$this->min}] given");
        }

        if ($this->step < 1) {
            throw InvalidConfigurationException::invalidQuantityRule("step must be at least 1, [{$this->step}] given");
        }

        if ($this->max !== null && $this->max < $this->min) {
            throw InvalidConfigurationException::invalidQuantityRule("max [{$this->max}] is below min [{$this->min}]");
        }
    }

    public function isValid(int $quantity): bool
    {
        return $quantity >= $this->min
            && ($quantity - $this->min) % $this->step === 0
            && ($this->max === null || $quantity <= $this->max);
    }

    /**
     * The nearest valid quantity at or above the given one; when that would
     * exceed max, the largest valid quantity not above max.
     */
    public function normalize(int $quantity): int
    {
        if ($quantity <= $this->min) {
            return $this->min;
        }

        $up = $this->min + intdiv($quantity - $this->min + $this->step - 1, $this->step) * $this->step;

        if ($this->max !== null && $up > $this->max) {
            return $this->min + intdiv($this->max - $this->min, $this->step) * $this->step;
        }

        return $up;
    }

    /**
     * @return array{min: int, step: int, max: int|null}
     */
    public function toArray(): array
    {
        return ['min' => $this->min, 'step' => $this->step, 'max' => $this->max];
    }

    public static function fromArray(array $data): self
    {
        foreach (['min', 'step', 'max'] as $key) {
            $value = $data[$key] ?? null;

            if ($value !== null && ! is_int($value)) {
                throw InvalidConfigurationException::invalidQuantityRule("[{$key}] must be an integer");
            }
        }

        return new self(
            min: $data['min'] ?? 1,
            step: $data['step'] ?? 1,
            max: $data['max'] ?? null,
        );
    }
}
