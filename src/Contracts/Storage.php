<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Contracts;

interface Storage
{
    public function read(string $list): array;

    public function write(string $list, array $payload): void;

    public function forget(string $list): void;
}
