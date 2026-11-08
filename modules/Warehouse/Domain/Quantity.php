<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Domain;

/**
 * bcmath-backed decimal arithmetic for stock quantities, per the "no
 * float" policy applied project-wide (see Pricing\Domain\Decimal, which
 * this intentionally does not import — private per-module domain logic,
 * see docs/knowledge-base/02-architecture.md).
 *
 * Unlike Pricing's Decimal, quantities here may legitimately be negative:
 * on-hand stock goes negative once a backordered reservation is fulfilled
 * (oversold on purpose), and movement deltas/corrections can go either way.
 */
final class Quantity
{
    private const SCALE = 6;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::compare($a, '0') > 0;
    }

    public static function isZero(string $a): bool
    {
        return self::compare($a, '0') === 0;
    }
}
