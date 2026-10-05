<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Contracts;

use TimurTurdyev\SimpleCart\ListStatus;

interface SupportsLifecycle
{
    /**
     * Closes the active list with the given status instead of deleting it and
     * returns the payload of exactly the record that was closed ([] when the
     * list had no record). The next write starts a new active list.
     */
    public function close(string $list, ListStatus $status, ?string $reference = null): array;
}
