<?php

declare(strict_types=1);

namespace Tests\Unit\Cart;

use Litecommerce\Modules\Cart\Domain\AdjustmentAllocator;
use PHPUnit\Framework\TestCase;

final class AdjustmentAllocatorTest extends TestCase
{
    public function test_shares_add_up_exactly(): void
    {
        $shares = AdjustmentAllocator::split('10.00', ['10.00', '10.00', '10.00'], 2);

        $this->assertSame(['3.34', '3.33', '3.33'], $shares);
        $this->assertSame('10.00', bcadd(bcadd($shares[0], $shares[1], 2), $shares[2], 2));
    }

    public function test_shares_follow_the_weights(): void
    {
        $this->assertSame(['2.50', '7.50'], AdjustmentAllocator::split('10.00', ['10.00', '30.00'], 2));
    }

    public function test_a_discount_is_split_with_its_sign(): void
    {
        $shares = AdjustmentAllocator::split('-1.00', ['1.00', '1.00', '1.00'], 2);

        $this->assertSame(['-0.34', '-0.33', '-0.33'], $shares);
    }

    public function test_zero_weights_split_equally(): void
    {
        $this->assertSame(['2.50', '2.50'], AdjustmentAllocator::split('5.00', ['0.00', '0.00'], 2));
    }

    public function test_a_currency_without_decimals_gives_whole_units(): void
    {
        $shares = AdjustmentAllocator::split('10', ['1', '1', '1'], 0);

        $this->assertSame(['4', '3', '3'], $shares);
    }

    public function test_an_empty_weight_list_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AdjustmentAllocator::split('1.00', [], 2);
    }
}
