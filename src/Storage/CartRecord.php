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
        return config('simple_cart.database.table', 'simple_cart_lists');
    }

    public function getConnectionName(): ?string
    {
        return config('simple_cart.database.connection') ?? parent::getConnectionName();
    }
}
