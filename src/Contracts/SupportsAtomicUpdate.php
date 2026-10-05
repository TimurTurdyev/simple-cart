<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

use Closure;

interface SupportsAtomicUpdate
{
    /**
     * Applies the mutator to the freshest stored payload and writes the result
     * only if nobody changed the list in between; otherwise the mutator runs
     * again on the newer payload. An empty result removes the list.
     *
     * @param Closure(array): array $mutator
     */
    public function update(string $list, Closure $mutator): array;
}
