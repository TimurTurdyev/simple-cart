<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleCart\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimurTurdyev\SimpleCart\Exceptions\InvalidConfigurationException;
use TimurTurdyev\SimpleCart\Support\QuantityRule;

final class QuantityRuleTest extends TestCase
{
    public static function validity(): iterable
    {
        yield 'below min' => [new QuantityRule(5), 4, false];
        yield 'at min' => [new QuantityRule(5), 5, true];
        yield 'on step grid' => [new QuantityRule(2, 4), 10, true];
        yield 'off step grid' => [new QuantityRule(2, 4), 8, false];
        yield 'above max' => [new QuantityRule(1, 1, 10), 11, false];
        yield 'at max' => [new QuantityRule(1, 1, 10), 10, true];
    }

    #[DataProvider('validity')]
    public function test_is_valid(QuantityRule $rule, int $quantity, bool $expected): void
    {
        $this->assertSame($expected, $rule->isValid($quantity));
    }

    public static function normalization(): iterable
    {
        yield 'packing: 70 by 66 rounds up' => [new QuantityRule(66, 66), 70, 132];
        yield 'below min lifts to min' => [new QuantityRule(66, 66), 1, 66];
        yield 'valid stays' => [new QuantityRule(66, 66), 132, 132];
        yield 'grid from min' => [new QuantityRule(2, 4), 7, 10];
        yield 'rounding up past max falls to largest valid' => [new QuantityRule(2, 4, 12), 12, 10];
        yield 'above max' => [new QuantityRule(1, 1, 10), 50, 10];
    }

    #[DataProvider('normalization')]
    public function test_normalize(QuantityRule $rule, int $quantity, int $expected): void
    {
        $this->assertSame($expected, $rule->normalize($quantity));
        $this->assertTrue($rule->isValid($rule->normalize($quantity)));
    }

    public function test_roundtrip(): void
    {
        $rule = new QuantityRule(2, 4, 12);

        $this->assertEquals($rule, QuantityRule::fromArray($rule->toArray()));
        $this->assertEquals(new QuantityRule(), QuantityRule::fromArray([]));
    }

    public static function invalidRules(): iterable
    {
        yield 'zero min' => [fn () => new QuantityRule(0)];
        yield 'zero step' => [fn () => new QuantityRule(1, 0)];
        yield 'max below min' => [fn () => new QuantityRule(5, 1, 4)];
        yield 'string in payload' => [fn () => QuantityRule::fromArray(['min' => '5'])];
    }

    #[DataProvider('invalidRules')]
    public function test_rejects_invalid_rules(\Closure $make): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $make();
    }
}
