<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Domain;

/**
 * bcmath-backed decimal arithmetic for summing already-rounded per-line
 * money amounts from Pricing\Contracts\PriceQuoteData into cart/draft
 * totals, per the "no float" policy applied project-wide. Mirrors
 * Pricing\Domain\Decimal, which this intentionally does not import —
 * private per-module domain logic, see docs/knowledge-base/02-architecture.md.
 */
final class Decimal
{
    private const INTERNAL_SCALE = 10;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::INTERNAL_SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::INTERNAL_SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::INTERNAL_SCALE);
    }

    public static function div(string $a, string $b): string
    {
        return bcdiv($a, $b, self::INTERNAL_SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::INTERNAL_SCALE);
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0') > 0;
    }

    /** Commercial rounding to the currency's decimal places: half away from zero, so a negative amount mirrors a positive one. */
    public static function roundHalfUp(string $amount, int $decimals): string
    {
        $negative = str_starts_with($amount, '-');
        $epsilon = '0.'.str_repeat('0', $decimals).'5';
        $bumped = bcadd(ltrim($amount, '-'), $epsilon, $decimals + 1);
        $rounded = bcadd($bumped, '0', $decimals);

        return $negative && bccomp($rounded, '0', $decimals) !== 0 ? '-'.$rounded : $rounded;
    }
}
