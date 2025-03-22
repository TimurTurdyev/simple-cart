<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart;

final readonly class ListPolicy
{
    public function __construct(
        public ListMode $mode = ListMode::Append,
        public ?int $limit = null,
    ) {
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            mode: ListMode::from($config['policy'] ?? 'append'),
            limit: $config['limit'] ?? null,
        );
    }
}
