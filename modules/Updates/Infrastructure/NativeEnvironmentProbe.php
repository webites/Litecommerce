<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Litecommerce\Modules\Updates\Application\EnvironmentProbe;

final class NativeEnvironmentProbe implements EnvironmentProbe
{
    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function hasExtension(string $extension): bool
    {
        return extension_loaded($extension);
    }

    public function isWritable(string $path): bool
    {
        $absolute = base_path($path);

        return ! file_exists($absolute) || is_writable($absolute);
    }

    public function freeBytes(): ?int
    {
        $free = @disk_free_space(base_path());

        return $free === false ? null : (int) $free;
    }
}
