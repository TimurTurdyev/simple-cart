<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Contracts;

use TimurTurdyev\Cart\ListPolicy;
use TimurTurdyev\Cart\MergeStrategy;

interface SupportsOwnerMerge
{
    /**
     * @param array<string, ListPolicy> $policies
     */
    public function mergeOwners(string $from, string $to, MergeStrategy $strategy, array $policies): void;
}
