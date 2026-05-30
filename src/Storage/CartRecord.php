<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Storage;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TimurTurdyev\SimpleCart\Cart;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\ListStatus;

/**
 * @property string $owner
 * @property string $list
 * @property array|null $payload
 * @property int $version
 * @property ListStatus $status
 * @property string $slot
 * @property string|null $reference
 *
 * @method static Builder<self> active()
 * @method static Builder<self> status(ListStatus ...$statuses)
 * @method static Builder<self> list(string $name)
 * @method static Builder<self> idleSince(DateTimeInterface $before)
 * @method static Builder<self> owner(string $owner)
 */
final class CartRecord extends Model
{
    public const string ACTIVE_SLOT = '';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'version' => 'integer',
            'status' => ListStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('simple_cart.database.table', 'simple_cart_lists');
    }

    public function getConnectionName(): ?string
    {
        return config('simple_cart.database.connection') ?? parent::getConnectionName();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('slot', self::ACTIVE_SLOT);
    }

    public function scopeStatus(Builder $query, ListStatus ...$statuses): void
    {
        $query->whereIn('status', array_map(fn (ListStatus $status): string => $status->value, $statuses));
    }

    public function scopeList(Builder $query, string $name): void
    {
        $query->where('list', $name);
    }

    /**
     * Pair with IdentityManager::ownerFor() to find a user's lists.
     */
    public function scopeOwner(Builder $query, string $owner): void
    {
        $query->where('owner', $owner);
    }

    public function scopeIdleSince(Builder $query, DateTimeInterface $before): void
    {
        $query->where('updated_at', '<', $before);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = ($this->payload ?? [])['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    public function cart(?ListPolicy $policy = null): Cart
    {
        return Cart::fromArray($policy ?? new ListPolicy(), $this->payload ?? []);
    }
}
