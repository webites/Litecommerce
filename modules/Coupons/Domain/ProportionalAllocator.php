<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Domain;

/**
 * Splits one amount across several weighted keys using the largest-remainder
 * method: floor every proportional share to the currency's precision, then
 * hand the leftover minimal units one at a time to the entries with the
 * largest truncated fraction. Ties resolve by the caller's array order (a
 * stable sort — PHP's usort has been stable since 8.0), which the caller
 * must build from a stable key (e.g. ascending variant id), so a repeated
 * computation over the same input always reaches the same result — "Remis
 * rozstrzyga stabilny identyfikator pozycji" (04-pricing-coupons.md).
 */
final class ProportionalAllocator
{
    /**
     * @param  array<int|string, string>  $weights  Ordered key => positive weight. A
     *                                              zero/negative weight always gets 0.
     * @return array<int|string, string> key => allocated amount, summing to
     *                                   min($total, sum of weights)
     */
    public static function allocate(string $total, array $weights, int $decimals): array
    {
        $result = [];
        foreach (array_keys($weights) as $key) {
            $result[$key] = '0';
        }

        if ($weights === [] || ! Decimal::isPositive($total)) {
            return $result;
        }

        $sumWeights = '0';
        foreach ($weights as $weight) {
            if (Decimal::isPositive($weight)) {
                $sumWeights = Decimal::add($sumWeights, $weight);
            }
        }

        if (! Decimal::isPositive($sumWeights)) {
            return $result;
        }

        $cappedTotal = Decimal::roundHalfUp(Decimal::min($total, $sumWeights), $decimals);

        $remainders = [];
        $sumFloors = '0';

        foreach ($weights as $key => $weight) {
            if (! Decimal::isPositive($weight)) {
                continue;
            }

            $raw = Decimal::div(Decimal::mul($cappedTotal, $weight), $sumWeights);
            // bcadd(..., $decimals) truncates rather than rounds — exactly
            // the floor step the largest-remainder method needs, since
            // every amount here is non-negative.
            $floor = bcadd($raw, '0', $decimals);
            $result[$key] = $floor;
            $remainders[$key] = Decimal::sub($raw, $floor);
            $sumFloors = Decimal::add($sumFloors, $floor);
        }

        $leftover = Decimal::sub($cappedTotal, $sumFloors);
        $unit = $decimals === 0 ? '1' : '0.'.str_repeat('0', $decimals - 1).'1';
        $leftoverUnits = (int) Decimal::roundHalfUp(Decimal::div($leftover, $unit), 0);

        if ($leftoverUnits <= 0) {
            return $result;
        }

        $ordered = array_keys($remainders);
        usort($ordered, static fn ($a, $b): int => Decimal::compare($remainders[$b], $remainders[$a]));

        for ($i = 0; $i < $leftoverUnits && $i < count($ordered); $i++) {
            $key = $ordered[$i];
            $result[$key] = bcadd($result[$key], $unit, $decimals);
        }

        return $result;
    }
}
