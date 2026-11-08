<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Domain;

/**
 * bcmath-backed decimal arithmetic, per the "no float" policy in
 * docs/knowledge-base/04-pricing-coupons.md. Mirrors Pricing\Domain\Decimal
 * — private per-module domain logic, not shared across the boundary (see
 * docs/knowledge-base/02-architecture.md).
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

    /** Commercial (half up) rounding to the currency's decimal places. */
    public static function roundHalfUp(string $amount, int $decimals): string
    {
        $epsilon = '0.'.str_repeat('0', $decimals).'5';
        $bumped = bcadd($amount, $epsilon, $decimals + 1);

        return bcadd($bumped, '0', $decimals);
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0') > 0;
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? $a : $b;
    }

    public static function min(string $a, string $b): string
    {
        return self::compare($a, $b) <= 0 ? $a : $b;
    }
}
