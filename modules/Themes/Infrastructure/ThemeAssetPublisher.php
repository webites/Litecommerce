<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Infrastructure;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Copies an extracted theme's assets/ folder from the private storage disk
 * (where the ZIP was unpacked, never under the webroot — same as a
 * plugin's own files) onto the public disk, so a theme's built CSS/JS is
 * reachable at a stable, predictable URL without exposing the rest of the
 * package (Blade views, manifest.json).
 */
final class ThemeAssetPublisher
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $privateDisk,
        private readonly string $privateBasePath,
        private readonly string $publicDisk,
        private readonly string $publicPath,
    ) {}

    public function publish(string $themeId): void
    {
        $sourcePath = Storage::disk($this->privateDisk)->path("{$this->privateBasePath}/{$themeId}/assets");

        if (! $this->files->isDirectory($sourcePath)) {
            return;
        }

        $targetPath = Storage::disk($this->publicDisk)->path("{$this->publicPath}/{$themeId}");
        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->copyDirectory($sourcePath, $targetPath);
    }

    public function unpublish(string $themeId): void
    {
        Storage::disk($this->publicDisk)->deleteDirectory("{$this->publicPath}/{$themeId}");
    }
}
