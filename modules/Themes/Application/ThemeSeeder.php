<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Application;

use Litecommerce\Modules\Themes\Contracts\ThemeCatalog;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Domain\ThemeManifestParser;
use ZipArchive;

/**
 * Reads the manifest id up front so a retry after a partial previous
 * attempt (row created but never activated — e.g. the installer's step
 * wrapper rolled back the DB write but not the already-extracted files,
 * same retry-safety shape as every other install step) does not try to
 * re-upload a package whose id already exists.
 */
final class ThemeSeeder implements ThemeInstaller
{
    public function __construct(
        private readonly UploadThemePackage $upload,
        private readonly ActivateTheme $activate,
        private readonly ThemeManifestParser $manifests,
        private readonly ThemeCatalog $themes,
    ) {}

    public function installAndActivate(string $zipPath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $manifestJson = (string) $zip->getFromName('manifest.json');
        $zip->close();

        $manifestId = $this->manifests->parse($manifestJson)->id;
        $existing = $this->themes->find($manifestId);

        if ($existing === null) {
            $this->upload->execute($zipPath);
        } elseif ($existing->status === ThemeStatus::Active) {
            return;
        }

        $this->activate->execute($manifestId);
    }
}
