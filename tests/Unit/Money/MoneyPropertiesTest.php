<?php

declare(strict_types=1);

namespace Tests\Unit\Money;

use Litecommerce\Modules\Coupons\Domain\ProportionalAllocator;
use Litecommerce\Modules\Pricing\Domain\Decimal;
use PHPUnit\Framework\TestCase;

/**
 * Seeded pseudo-random inputs: every run covers the same cases, so a failure is reproducible.
 */
final class MoneyPropertiesTest extends TestCase
{
    private function amount(int $maxMinor, int $decimals): string
    {
        $minor = mt_rand(1, $maxMinor);

        return $decimals === 0 ? (string) $minor : bcdiv((string) $minor, bcpow('10', (string) $decimals), $decimals);
    }

    public function test_allocation_always_sums_to_the_capped_total_and_never_exceeds_a_weight(): void
    {
        mt_srand(20261106);

        for ($case = 0; $case < 500; $case++) {
            $decimals = [0, 2, 2, 2, 3][mt_rand(0, 4)];
            $weights = [];
            foreach (range(1, mt_rand(1, 12)) as $i) {
                $weights["line-{$i}"] = $this->amount(500000, $decimals);
            }
            $total = $this->amount(800000, $decimals);

            $result = ProportionalAllocator::allocate($total, $weights, $decimals);

            $sumWeights = array_reduce($weights, static fn (string $carry, string $w): string => bcadd($carry, $w, $decimals), '0');
            $expected = bccomp($total, $sumWeights, $decimals) <= 0 ? $total : $sumWeights;
            $sum = array_reduce($result, static fn (string $carry, string $v): string => bcadd($carry, $v, $decimals), '0');

            $this->assertSame(bcadd($expected, '0', $decimals), bcadd($sum, '0', $decimals), "case {$case}: allocation must sum to the capped total");
            foreach ($result as $key => $share) {
                $this->assertGreaterThanOrEqual(0, bccomp($share, '0', $decimals), "case {$case}: negative share");
                $this->assertLessThanOrEqual(0, bccomp($share, $weights[$key], $decimals), "case {$case}: share above the weight");
            }
            $this->assertSame($result, ProportionalAllocator::allocate($total, $weights, $decimals), "case {$case}: allocation must be deterministic");
        }
    }

    public function test_rounding_is_idempotent_monotonic_and_never_off_by_more_than_half_a_unit(): void
    {
        mt_srand(77);

        for ($case = 0; $case < 500; $case++) {
            $decimals = mt_rand(0, 4);
            $raw = bcdiv((string) mt_rand(0, 99999999), (string) mt_rand(1, 9999), 8);
            $rounded = Decimal::roundHalfUp($raw, $decimals);

            $this->assertSame($rounded, Decimal::roundHalfUp($rounded, $decimals), 'idempotent');
            $half = bcdiv('1', bcmul('2', bcpow('10', (string) $decimals), 0), $decimals + 1);
            $difference = bcsub($rounded, $raw, 10);
            $this->assertLessThanOrEqual(0, bccomp(ltrim($difference, '-'), $half, 10), "case {$case}: error above half a unit");

            $bigger = bcadd($raw, '0.01', 8);
            $this->assertGreaterThanOrEqual(0, bccomp(Decimal::roundHalfUp($bigger, $decimals), $rounded, $decimals), 'monotonic');
        }
    }

    public function test_the_proportional_split_with_a_final_remainder_always_rebuilds_the_original_amount(): void
    {
        mt_srand(4242);

        for ($case = 0; $case < 300; $case++) {
            $original = $this->amount(10000000, 2);
            $quantity = mt_rand(1, 40);
            $remaining = $quantity;
            $allocated = '0';

            while ($remaining > 0) {
                $take = mt_rand(1, $remaining);
                $isFinal = $take === $remaining;
                $share = $isFinal
                    ? Decimal::roundHalfUp(Decimal::sub($original, $allocated), 2)
                    : Decimal::roundHalfUp(Decimal::mul($original, Decimal::div((string) $take, (string) $quantity)), 2);
                $allocated = bcadd($allocated, $share, 2);
                $remaining -= $take;
            }

            $this->assertSame(bcadd($original, '0', 2), $allocated, "case {$case}: partial refunds must rebuild the source value exactly");
        }
    }
}
