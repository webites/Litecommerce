<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

interface EnvironmentProbe
{
    public function phpVersion(): string;

    public function hasExtension(string $extension): bool;

    /** @param  string  $path  relative to the application root */
    public function isWritable(string $path): bool;

    public function freeBytes(): ?int;
}
