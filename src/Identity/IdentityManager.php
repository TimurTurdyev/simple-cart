<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Identity;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Manager;
use TimurTurdyev\SimpleCart\Contracts\CartIdentity;

final class IdentityManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('simple_cart.identity.driver', 'cookie');
    }

    /**
     * Owner id of the user signed in through the cart guard, or null for a
     * guest.
     */
    public function authOwner(): ?string
    {
        $id = $this->container->make('auth')->guard($this->authGuard())->id();

        return $id === null ? null : $this->ownerFor($id);
    }

    /**
     * Owner id under which the cart of the given user is stored, e.g. to find
     * a customer's cart from an admin screen.
     */
    public function ownerFor(Authenticatable|int|string $user): string
    {
        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return (string) $this->config->get('simple_cart.identity.auth.prefix', '').$id;
    }

    /**
     * The guard whose users own carts; null means the default guard.
     */
    public function authGuard(): ?string
    {
        return $this->config->get('simple_cart.identity.auth.guard');
    }

    protected function createSessionDriver(): CartIdentity
    {
        return new SessionIdentity($this->container->make('session.store'));
    }

    protected function createCookieDriver(): CartIdentity
    {
        $config = $this->config->get('simple_cart.identity.cookie', []);
        $name = (string) ($config['name'] ?? 'simple_cart_id');

        return new CookieIdentity(
            cookies: $this->container->make('cookie'),
            cookieValue: function () use ($name): ?string {
                $value = $this->container->make('request')->cookies->get($name);

                return is_string($value) && $value !== '' ? $value : null;
            },
            name: $name,
            ttlMinutes: (int) ($config['ttl_minutes'] ?? 60 * 24 * 30),
            pattern: (string) ($config['pattern'] ?? CookieIdentity::DEFAULT_PATTERN),
        );
    }
}
