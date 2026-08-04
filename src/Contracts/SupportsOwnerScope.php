<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Contracts;

interface SupportsOwnerScope
{
    /**
     * Returns a storage bound to an explicit owner id, bypassing the current
     * request identity. Used for bulk operations such as console imports.
     */
    public function forOwner(string $owner): Storage;
}
