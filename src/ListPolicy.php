<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart;

use TimurTurdyev\SimpleCart\Support\QuantityRule;

final readonly class ListPolicy
{
    /**
     * @param list<string>|null $keyOptions Option keys that make up the line
     *                                      identity; null means every option.
     */
    public function __construct(
        public ListMode $mode = ListMode::Append,
        public ?int $limit = null,
        public ?array $keyOptions = null,
        public ?QuantityRule $quantityRule = null,
    ) {
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            mode: ListMode::from($config['policy'] ?? 'append'),
            limit: $config['limit'] ?? null,
            keyOptions: $config['key_options'] ?? null,
            quantityRule: is_array($config['quantity'] ?? null) ? QuantityRule::fromArray($config['quantity']) : null,
        );
    }

    /**
     * Quantity rules apply to append lists only: toggle lists (wishlist,
     * compare) hold one piece per line. The line's own rule wins over the
     * list default.
     */
    public function quantityRuleFor(Line $line): ?QuantityRule
    {
        if ($this->mode !== ListMode::Append) {
            return null;
        }

        return $line->quantityRule ?? $this->quantityRule;
    }
}
