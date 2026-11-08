<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Application;

use App\Support\Packaging\PackageValidationException;
use App\Support\Packaging\ZipPackageValidator;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Themes\Contracts\ThemeInstallationException;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Domain\ThemeManifestParser;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;
use ZipArchive;

/**
 * Validate and extract only — no assets published, nothing activated yet.
 * The theme row starts Installed, never having been active.
 */
final class UploadThemePackage
{
    public function __construct(
        private readonly ThemeManifestParser $manifests,
        private readonly int $maxSizeBytes,
        private readonly int $maxFileCount,
        private readonly string $disk,
        private readonly string $basePath,
    ) {}

    public function execute(string $uploadedZipPath): Theme
    {
        $zip = new ZipArchive;
        if ($zip->open($uploadedZipPath) !== true) {
            throw new ThemeInstallationException('package_unreadable', 'Could not open the uploaded file as a ZIP archive.');
        }

        try {
            try {
                $validator = new ZipPackageValidator($this->maxSizeBytes, $this->maxFileCount);
                $manifestJson = $validator->validate($zip);
            } catch (PackageValidationException $exception) {
                throw new ThemeInstallationException($exception->errorCode, $exception->getMessage(), $exception->params);
            }
            $manifest = $this->manifests->parse($manifestJson);

            if (Theme::query()->whereKey($manifest->id)->exists()) {
                throw new ThemeInstallationException('package_already_installed', "Theme \"{$manifest->id}\" is already installed. Remove it first to upload a different version.", ['id' => $manifest->id]);
            }

            $relativePath = $this->basePath.'/'.$manifest->id;
            $targetPath = Storage::disk($this->disk)->path($relativePath);

            if (! is_dir($targetPath) && ! mkdir($targetPath, 0755, true) && ! is_dir($targetPath)) {
                throw new ThemeInstallationException('package_storage_unwritable', 'Could not create the target directory for the theme.');
            }

            try {
                $validator->extract($zip, $targetPath);
            } catch (PackageValidationException $exception) {
                throw new ThemeInstallationException($exception->errorCode, $exception->getMessage(), $exception->params);
            }

            return Theme::query()->create([
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'engine' => $manifest->engine,
                'status' => ThemeStatus::Installed->value,
                'manifest' => json_decode($manifestJson, true),
                'package_sha256' => hash_file('sha256', $uploadedZipPath) ?: null,
                // A theme has no separate install() step like a plugin does
                // (it ships no PHP) — status=installed IS the installed
                // state from the moment upload succeeds, so installed_at
                // is set here rather than staying null indefinitely.
                'installed_at' => now(),
            ]);
        } finally {
            $zip->close();
        }
    }
}
