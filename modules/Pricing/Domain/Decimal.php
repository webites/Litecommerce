<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Domain;

/**
 * bcmath-backed decimal arithmetic for money/quantity values, per the
 * "no float" policy in docs/knowledge-base/04-pricing-coupons.md.
 *
 * Assumes non-negative amounts — nothing in Pricing ever computes a
 * negative price, VAT amount or quantity.
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

    public static function isPositive(string $a): bool
    {
        return self::compare($a, '0') > 0;
    }

    /**
     * Commercial (half up) rounding: a value exactly at the half of the
     * smallest unit rounds up.
     */
    public static function roundHalfUp(string $amount, int $decimals): string
    {
        $epsilon = '0.'.str_repeat('0', $decimals).'5';
        $bumped = bcadd($amount, $epsilon, $decimals + 1);

        return bcadd($bumped, '0', $decimals);
    }
}
