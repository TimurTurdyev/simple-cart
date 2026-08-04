<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\MergeStrategy;

interface SupportsOwnerMerge
{
    /**
     * @param array<string, ListPolicy> $policies
     */
    public function mergeOwners(string $from, string $to, MergeStrategy $strategy, array $policies): void;
}
