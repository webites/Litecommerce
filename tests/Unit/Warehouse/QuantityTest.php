<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use Litecommerce\Modules\Warehouse\Domain\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_add_and_sub(): void
    {
        $this->assertSame('12.000000', Quantity::add('10', '2'));
        $this->assertSame('8.000000', Quantity::sub('10', '2'));
    }

    public function test_sub_can_go_negative_for_oversold_backorder_stock(): void
    {
        $this->assertSame('-3.000000', Quantity::sub('2', '5'));
    }

    public function test_compare(): void
    {
        $this->assertSame(-1, Quantity::compare('1', '2'));
        $this->assertSame(0, Quantity::compare('2', '2'));
        $this->assertSame(1, Quantity::compare('3', '2'));
    }

    public function test_is_positive(): void
    {
        $this->assertTrue(Quantity::isPositive('0.000001'));
        $this->assertFalse(Quantity::isPositive('0'));
        $this->assertFalse(Quantity::isPositive('-1'));
    }

    public function test_is_zero(): void
    {
        $this->assertTrue(Quantity::isZero('0'));
        $this->assertTrue(Quantity::isZero('0.000000'));
        $this->assertFalse(Quantity::isZero('0.000001'));
    }
}
