<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use Illuminate\Support\Manager;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;

final class IdentityManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('cart.identity.driver', 'session');
    }

    protected function createSessionDriver(): CartIdentity
    {
        return new SessionIdentity($this->container->make('session.store'));
    }

    protected function createCookieDriver(): CartIdentity
    {
        $config = $this->config->get('cart.identity.cookie', []);
        $name = (string) ($config['name'] ?? 'cart_id');

        return new CookieIdentity(
            cookies: $this->container->make('cookie'),
            cookieValue: function () use ($name): ?string {
                $value = $this->container->make('request')->cookies->get($name);

                return is_string($value) && $value !== '' ? $value : null;
            },
            name: $name,
            ttlMinutes: (int) ($config['ttl_minutes'] ?? 60 * 24 * 30),
        );
    }
}
