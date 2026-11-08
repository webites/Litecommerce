<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Infrastructure;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Packages resources/themes/default-storefront/ into a real ZIP file at
 * install time, so the installer hands Themes\Contracts\ThemeInstaller
 * exactly the same artifact shape a merchant's own upload through the
 * panel would produce — no parallel "built-in theme" code path with
 * different rules (see "Domyślny motyw seedowany automatycznie",
 * docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 */
final class DefaultThemePackager
{
    /** @return string path to the built ZIP file — caller deletes it once done */
    public function package(): string
    {
        $sourcePath = base_path('resources/themes/default-storefront');
        $zipPath = sys_get_temp_dir().'/default-theme-'.uniqid().'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            $relativePath = substr($file->getPathname(), strlen($sourcePath) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        return $zipPath;
    }
}
