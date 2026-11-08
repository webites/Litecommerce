<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Domain;

/** Splits one signed amount across weighted lines so the parts add up exactly (largest remainder). */
final class AdjustmentAllocator
{
    /**
     * @param  list<string>  $weights  Non-negative decimals; all zero means equal shares.
     * @return list<string> One share per weight, in the same order.
     */
    public static function split(string $amount, array $weights, int $decimals): array
    {
        if ($weights === []) {
            throw new \InvalidArgumentException('Nothing to split the amount across.');
        }

        $rounded = Decimal::roundHalfUp($amount, $decimals);
        $negative = str_starts_with($rounded, '-');
        $factor = bcpow('10', (string) $decimals, 0);
        $units = bcmul(ltrim($rounded, '-'), $factor, 0);

        $scaled = array_map(static fn (string $weight): string => bcmul($weight, '1000000', 0), $weights);
        if (bccomp(array_reduce($scaled, static fn (string $sum, string $w): string => bcadd($sum, $w, 0), '0'), '0', 0) === 0) {
            $scaled = array_fill(0, count($weights), '1');
        }
        $totalWeight = array_reduce($scaled, static fn (string $sum, string $w): string => bcadd($sum, $w, 0), '0');

        $shares = [];
        $remainders = [];
        $assigned = '0';
        foreach ($scaled as $index => $weight) {
            $numerator = bcmul($units, $weight, 0);
            $shares[$index] = bcdiv($numerator, $totalWeight, 0);
            $remainders[$index] = bcmod($numerator, $totalWeight, 0);
            $assigned = bcadd($assigned, $shares[$index], 0);
        }

        $leftover = (int) bcsub($units, $assigned, 0);
        $order = array_keys($remainders);
        usort($order, static fn (int $a, int $b): int => bccomp($remainders[$b], $remainders[$a], 0) ?: $a <=> $b);
        foreach (array_slice($order, 0, $leftover) as $index) {
            $shares[$index] = bcadd($shares[$index], '1', 0);
        }

        return array_map(
            static fn (string $share): string => ($negative && bccomp($share, '0', 0) !== 0 ? '-' : '').bcdiv($share, $factor, $decimals),
            $shares,
        );
    }
}
