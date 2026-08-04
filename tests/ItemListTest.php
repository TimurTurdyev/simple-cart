<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests;

use PHPUnit\Framework\TestCase;
use TimurTurdyev\SimpleCart\Exceptions\ListLimitException;
use TimurTurdyev\SimpleCart\ItemList;
use TimurTurdyev\SimpleCart\Line;
use TimurTurdyev\SimpleCart\ListMode;
use TimurTurdyev\SimpleCart\ListPolicy;
use TimurTurdyev\SimpleCart\Support\Price;

final class ItemListTest extends TestCase
{
    public function test_append_merges_quantities(): void
    {
        $list = $this->appendList()
            ->add($this->line(1, quantity: 2))
            ->add($this->line(1, quantity: 3));

        $this->assertSame(1, $list->count());
        $this->assertSame(5, $list->totalQuantity());
    }

    public function test_append_keeps_lines_with_different_options_apart(): void
    {
        $list = $this->appendList()
            ->add($this->line(1, options: ['color' => 'red']))
            ->add($this->line(1, options: ['color' => 'blue']));

        $this->assertSame(2, $list->count());
    }

    public function test_toggle_mode_add_is_idempotent(): void
    {
        $list = $this->toggleList()
            ->add($this->line(1))
            ->add($this->line(1, quantity: 5));

        $this->assertSame(1, $list->count());
        $this->assertSame(1, $list->totalQuantity());
    }

    public function test_toggle_adds_and_removes(): void
    {
        $line = $this->line(1);
        $list = $this->toggleList()->toggle($line);

        $this->assertTrue($list->has($line->id));

        $list = $list->toggle($line);

        $this->assertFalse($list->has($line->id));
        $this->assertTrue($list->isEmpty());
    }

    public function test_limit_blocks_new_lines(): void
    {
        $list = $this->toggleList(limit: 2)
            ->add($this->line(1))
            ->add($this->line(2));

        $this->expectException(ListLimitException::class);

        $list->add($this->line(3));
    }

    public function test_limit_allows_updating_existing_line(): void
    {
        $list = $this->appendList(limit: 1)
            ->add($this->line(1))
            ->add($this->line(1, quantity: 2));

        $this->assertSame(3, $list->totalQuantity());
    }

    public function test_remove_get_and_clear(): void
    {
        $line = $this->line(1);
        $list = $this->appendList()->add($line)->add($this->line(2));

        $this->assertSame($line->id, $list->get($line->id)?->id);
        $this->assertSame(1, $list->remove($line->id)->count());
        $this->assertSame(2, $list->remove('missing')->count());
        $this->assertTrue($list->clear()->isEmpty());
    }

    public function test_subtotal(): void
    {
        $list = $this->appendList()
            ->add($this->line(1, price: 1000, quantity: 2))
            ->add($this->line(2, price: 500));

        $this->assertSame(2500, $list->subtotal()->minor());
    }

    public function test_operations_do_not_mutate_original(): void
    {
        $list = $this->appendList();
        $list->add($this->line(1));

        $this->assertTrue($list->isEmpty());
    }

    public function test_array_roundtrip(): void
    {
        $policy = new ListPolicy(ListMode::Append);
        $list = ItemList::make($policy)
            ->add($this->line(1, options: ['color' => 'red']))
            ->add($this->line(2, quantity: 3));

        $restored = ItemList::fromArray($policy, $list->toArray());

        $this->assertEquals($list->lines, $restored->lines);
    }

    private function appendList(?int $limit = null): ItemList
    {
        return ItemList::make(new ListPolicy(ListMode::Append, $limit));
    }

    private function toggleList(?int $limit = null): ItemList
    {
        return ItemList::make(new ListPolicy(ListMode::Toggle, $limit));
    }

    private function line(int $id, int $price = 100, int $quantity = 1, array $options = []): Line
    {
        return Line::of($id, "Item {$id}", Price::fromMinor($price), $quantity, $options);
    }
}
