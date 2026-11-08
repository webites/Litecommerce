<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Domain;

/**
 * bcmath-backed decimal arithmetic for cart line quantities, per the "no
 * float" policy applied project-wide (see Pricing\Domain\Decimal and
 * Warehouse\Domain\Quantity, which this intentionally does not import —
 * private per-module domain logic, see docs/knowledge-base/02-architecture.md).
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

    /**
     * Whether $quantity respects the variant's [minimum, maximum] bounds
     * and lands exactly on a $step increment above the minimum — e.g.
     * min=1, step=0.5 accepts 1, 1.5, 2 but not 1.2. A digital variant's
     * min=max=step="1" (enforced by Catalog) makes this a strict "exactly
     * one" check without any special-casing here.
     */
    public static function isWithinBounds(string $quantity, string $minimum, ?string $maximum, string $step): bool
    {
        if (self::compare($quantity, $minimum) < 0) {
            return false;
        }

        if ($maximum !== null && self::compare($quantity, $maximum) > 0) {
            return false;
        }

        if (self::compare($step, '0') <= 0) {
            return true;
        }

        $steps = bcdiv(self::sub($quantity, $minimum), $step, self::SCALE + 4);
        $rounded = bcadd($steps, '0', 0);

        return bccomp($steps, $rounded, self::SCALE) === 0;
    }

    /**
     * Clamps $quantity into the variant's [minimum, maximum] bounds
     * without re-validating the step — used when merging carts, where we
     * cap an already step-valid sum rather than reject it outright.
     */
    public static function clamp(string $quantity, string $minimum, ?string $maximum): string
    {
        if (self::compare($quantity, $minimum) < 0) {
            return $minimum;
        }

        if ($maximum !== null && self::compare($quantity, $maximum) > 0) {
            return $maximum;
        }

        return $quantity;
    }
}
