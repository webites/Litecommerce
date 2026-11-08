<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Infrastructure;

/**
 * File flag blocking a re-run of the installer. Deliberately outside the
 * database: the middleware must be able to check it before a database
 * even exists. Path goes through storage_path() — tests redirect it via
 * $this->app->useStoragePath(), no separate injection needed.
 */
final class InstallationLock
{
    public static function path(): string
    {
        return storage_path('installed.lock');
    }

    public static function exists(): bool
    {
        return file_exists(self::path());
    }

    public static function create(): void
    {
        file_put_contents(self::path(), (string) now());
    }
}
