<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Storage;

use Illuminate\Contracts\Session\Session;
use TimurTurdyev\Cart\Contracts\Storage;

final readonly class SessionStorage implements Storage
{
    public function __construct(
        private Session $session,
        private string $prefix = 'cart',
    ) {
    }

    public function read(string $list): array
    {
        return $this->session->get($this->key($list), []);
    }

    public function write(string $list, array $payload): void
    {
        if ($payload === []) {
            $this->forget($list);

            return;
        }

        $this->session->put($this->key($list), $payload);
    }

    public function forget(string $list): void
    {
        $this->session->forget($this->key($list));
    }

    private function key(string $list): string
    {
        return $this->prefix.'.'.$list;
    }
}
