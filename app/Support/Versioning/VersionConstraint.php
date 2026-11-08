<?php

declare(strict_types=1);

namespace App\Support\Versioning;

/**
 * Deliberately minimal semver constraint check — no `composer/semver`
 * dependency, since manifests are evaluated by the running application,
 * not by Composer ("Biblioteki PHP dostarczane w paczce, bez Composera u
 * klienta", docs/knowledge-base/08-extensions.md). Supports the handful of
 * forms a package author actually needs: `^1.2.3`, `~1.2.3`, comparison
 * operators, an exact version, or `*`/empty for "any".
 *
 * Shared by modules/Extensions and modules/Themes — both check a
 * package's "core" constraint and (for themes, a required plugin's)
 * version the same way. Lives outside modules/ for the same reason as
 * App\Support\Packaging\ZipPackageValidator: neither module may import the
 * other's Domain layer, and this is not domain logic of either.
 */
final class VersionConstraint
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            $base = substr($constraint, 1);

            return version_compare($version, $base, '>=') && self::segment($version, 0) === self::segment($base, 0);
        }

        if (str_starts_with($constraint, '~')) {
            $base = substr($constraint, 1);

            return version_compare($version, $base, '>=')
                && self::segment($version, 0) === self::segment($base, 0)
                && self::segment($version, 1) === self::segment($base, 1);
        }

        foreach (['>=', '<=', '>', '<'] as $operator) {
            if (str_starts_with($constraint, $operator)) {
                return version_compare($version, trim(substr($constraint, strlen($operator))), $operator);
            }
        }

        return version_compare($version, $constraint, '==');
    }

    private static function segment(string $version, int $index): string
    {
        return explode('.', $version)[$index] ?? '0';
    }
}
