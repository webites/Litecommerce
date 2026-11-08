<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;

/**
 * Checks the environment requirements set in P0-004
 * (docs/knowledge-base/10-operations.md — "Wymagania środowiskowe").
 */
final class EnvironmentRequirementsChecker implements EnvironmentRequirements
{
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
        'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'xmlwriter',
        'bcmath', 'intl', 'gd', 'zip', 'sodium',
    ];

    private const MINIMUM_PHP_VERSION = '8.3.0';

    public function minimumPhpVersion(): string
    {
        return self::MINIMUM_PHP_VERSION;
    }

    public function requiredExtensions(): array
    {
        return self::REQUIRED_EXTENSIONS;
    }

    public function check(): EnvironmentRequirementsResult
    {
        $missingExtensions = array_values(array_filter(
            self::REQUIRED_EXTENSIONS,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        $unwritablePaths = array_values(array_filter(
            [storage_path(), base_path('bootstrap/cache')],
            static fn (string $path): bool => ! is_writable($path),
        ));

        return new EnvironmentRequirementsResult(
            phpVersionSatisfied: version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>='),
            missingExtensions: $missingExtensions,
            unwritablePaths: $unwritablePaths,
        );
    }
}
