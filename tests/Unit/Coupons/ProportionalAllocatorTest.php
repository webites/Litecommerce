<?php

declare(strict_types=1);

namespace Tests\Unit\Coupons;

use Litecommerce\Modules\Coupons\Domain\ProportionalAllocator;
use PHPUnit\Framework\TestCase;

/**
 * Largest-remainder allocation — verified against the worked example in
 * docs/knowledge-base/04-pricing-coupons.md#przykłady-akceptacyjne-pln:
 * "Dwie pozycje brutto 12,30 (23%) i 10,80 (8%) oraz kupon 10,00: alokacje
 * kuponu wynoszą 5,32 i 4,68."
 */
final class ProportionalAllocatorTest extends TestCase
{
    public function test_matches_the_two_line_worked_example(): void
    {
        $result = ProportionalAllocator::allocate('10.00', ['a' => '12.30', 'b' => '10.80'], 2);

        $this->assertSame('5.32', $result['a']);
        $this->assertSame('4.68', $result['b']);
    }

    public function test_full_coverage_example(): void
    {
        // "Produkty 100,00 ... kupon 130,00: produkty otrzymują 100,00 rabatu."
        $result = ProportionalAllocator::allocate('130.00', ['only' => '100.00'], 2);

        $this->assertSame('100.00', $result['only']);
    }

    public function test_leftover_cent_goes_to_the_largest_remainder(): void
    {
        // Three equal-weight lines each worth at least 10/3: floor gives
        // 3.33 * 3 = 9.99, one cent left over must go to exactly one line,
        // resolved by stable array order on an exact tie.
        $result = ProportionalAllocator::allocate('10.00', ['a' => '100', 'b' => '100', 'c' => '100'], 2);

        $this->assertSame('10.00', bcadd(bcadd($result['a'], $result['b'], 2), $result['c'], 2));
        $this->assertSame('3.34', $result['a']);
        $this->assertSame('3.33', $result['b']);
        $this->assertSame('3.33', $result['c']);
    }

    public function test_total_never_exceeds_the_sum_of_weights(): void
    {
        $result = ProportionalAllocator::allocate('130.00', ['a' => '20.00', 'b' => '80.00'], 2);

        $this->assertSame('20.00', $result['a']);
        $this->assertSame('80.00', $result['b']);
    }

    public function test_zero_total_allocates_nothing(): void
    {
        $result = ProportionalAllocator::allocate('0', ['a' => '10.00'], 2);

        $this->assertSame('0', $result['a']);
    }

    public function test_no_weights_returns_empty(): void
    {
        $this->assertSame([], ProportionalAllocator::allocate('10.00', [], 2));
    }
}
