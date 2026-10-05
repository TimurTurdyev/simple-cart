<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Storage;

use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\ListStatus;
use TimurTurdyev\SimpleCart\Storage\CartRecord;
use TimurTurdyev\SimpleCart\Tests\TestCase;

final class CartRecordTest extends TestCase
{
    public function test_scopes_select_records_without_identity(): void
    {
        $this->record('guest-1', 'cart', ListStatus::Active, '', now()->subDays(40));
        $this->record('guest-2', 'cart', ListStatus::Active, '', now());
        $this->record('guest-3', 'wishlist', ListStatus::Active, '', now()->subDays(40));
        $this->record('guest-1', 'cart', ListStatus::Ordered, '99', now()->subDays(40));

        $this->assertSame(3, CartRecord::query()->active()->count());
        $this->assertSame(1, CartRecord::query()->status(ListStatus::Ordered)->count());
        $this->assertSame(4, CartRecord::query()->status(ListStatus::Active, ListStatus::Ordered)->count());
        $this->assertSame(['guest-1'], CartRecord::query()
            ->active()
            ->list('cart')
            ->idleSince(now()->subDays(30))
            ->pluck('owner')
            ->all());
    }

    public function test_cart_helper_hydrates_payload(): void
    {
        $record = $this->record('guest-1', 'cart', ListStatus::Active, '', now(), ['lines' => [[
            'id' => 'abc', 'purchasable_id' => 7, 'name' => 'Box', 'price' => 1500, 'quantity' => 2,
        ]]]);

        $cart = $record->cart(new ListPolicy());

        $this->assertSame(3000, $cart->subtotal()->minor());
        $this->assertSame(1, $record->cart()->count());
    }

    private function record(string $owner, string $list, ListStatus $status, string $slot, $updatedAt, array $payload = ['lines' => []]): CartRecord
    {
        $record = new CartRecord([
            'owner' => $owner,
            'list' => $list,
            'payload' => $payload,
            'status' => $status,
            'slot' => $slot,
        ]);
        $record->updated_at = $updatedAt;
        $record->timestamps = false;
        $record->created_at = $updatedAt;
        $record->save();

        return $record;
    }
}
