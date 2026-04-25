<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Exceptions;

use TimurTurdyev\SimpleCart\Support\QuantityRule;

final class QuantityRuleException extends CartException
{
    private function __construct(
        string $message,
        public readonly int $min,
        public readonly int $step,
        public readonly ?int $max,
        public readonly int $given,
    ) {
        parent::__construct($message);
    }

    public static function violated(QuantityRule $rule, int $given): self
    {
        $max = $rule->max === null ? 'none' : (string) $rule->max;

        return new self(
            "Quantity [{$given}] violates the rule: min {$rule->min}, step {$rule->step}, max {$max}.",
            $rule->min,
            $rule->step,
            $rule->max,
            $given,
        );
    }
}
