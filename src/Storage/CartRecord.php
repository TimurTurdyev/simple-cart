<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Storage;

use Illuminate\Database\Eloquent\Model;

final class CartRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function getTable(): string
    {
        return config('cart.database.table', 'cart_lists');
    }

    public function getConnectionName(): ?string
    {
        return config('cart.database.connection') ?? parent::getConnectionName();
    }
}
