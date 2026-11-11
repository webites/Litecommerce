<?php

declare(strict_types=1);

namespace Tests\Concerns;

use FilesystemIterator;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

trait InstallsStorefrontTheme
{
    private function installStorefrontTheme(string $themeId = 'default-storefront'): void
    {
        $sourceDir = base_path('resources/themes/'.$themeId);
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($sourceDir) + 1));
        }
        $zip->close();

        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();
    }
}
