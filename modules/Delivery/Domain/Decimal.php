<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Domain;

/**
 * bcmath-backed decimal arithmetic for money values, per the "no float"
 * policy in docs/knowledge-base/04-pricing-coupons.md.
 *
 * Assumes non-negative amounts — nothing in Delivery ever computes a
 * negative price or VAT amount.
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
