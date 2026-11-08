<?php

declare(strict_types=1);

namespace Tests\Unit\Cart;

use Litecommerce\Modules\Cart\Domain\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_add(): void
    {
        $this->assertSame('12.000000', Quantity::add('10', '2'));
    }

    public function test_is_within_bounds_respects_minimum_and_maximum(): void
    {
        $this->assertFalse(Quantity::isWithinBounds('0', '1', '10', '1'));
        $this->assertTrue(Quantity::isWithinBounds('1', '1', '10', '1'));
        $this->assertTrue(Quantity::isWithinBounds('10', '1', '10', '1'));
        $this->assertFalse(Quantity::isWithinBounds('11', '1', '10', '1'));
    }

    public function test_is_within_bounds_with_no_maximum(): void
    {
        $this->assertTrue(Quantity::isWithinBounds('1000', '1', null, '1'));
    }

    public function test_is_within_bounds_enforces_step(): void
    {
        $this->assertTrue(Quantity::isWithinBounds('1.5', '1', '5', '0.5'));
        $this->assertFalse(Quantity::isWithinBounds('1.2', '1', '5', '0.5'));
    }

    /**
     * Catalog enforces min=max=step="1" for digital variants — bounds
     * checking alone should reject a second unit without Cart needing
     * any digital-specific special case.
     */
    public function test_digital_style_bounds_reject_more_than_one(): void
    {
        $this->assertTrue(Quantity::isWithinBounds('1', '1', '1', '1'));
        $this->assertFalse(Quantity::isWithinBounds('2', '1', '1', '1'));
    }

    public function test_clamp(): void
    {
        $this->assertSame('1', Quantity::clamp('0', '1', '10'));
        $this->assertSame('10', Quantity::clamp('12', '1', '10'));
        $this->assertSame('5', Quantity::clamp('5', '1', '10'));
        $this->assertSame('5', Quantity::clamp('5', '1', null));
    }
}
