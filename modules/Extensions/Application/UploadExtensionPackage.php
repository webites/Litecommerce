<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use App\Support\Packaging\PackageValidationException;
use App\Support\Packaging\ZipPackageValidator;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStatus;
use Litecommerce\Modules\Extensions\Domain\ManifestParser;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use ZipArchive;

// Validates and extracts only — no migrations or provider boot yet; the row starts Disabled.
final class UploadExtensionPackage
{
    public function __construct(
        private readonly ManifestParser $manifests,
        private readonly int $maxSizeBytes,
        private readonly int $maxFileCount,
        private readonly string $disk,
        private readonly string $basePath,
    ) {}

    public function execute(string $uploadedZipPath): Extension
    {
        $zip = new ZipArchive;
        if ($zip->open($uploadedZipPath) !== true) {
            throw new ExtensionInstallationException('package_unreadable', 'Could not open the uploaded file as a ZIP archive.');
        }

        try {
            try {
                $validator = new ZipPackageValidator($this->maxSizeBytes, $this->maxFileCount);
                $manifestJson = $validator->validate($zip);
            } catch (PackageValidationException $exception) {
                throw new ExtensionInstallationException($exception->errorCode, $exception->getMessage(), $exception->params);
            }
            $manifest = $this->manifests->parse($manifestJson);

            if (Extension::query()->whereKey($manifest->id)->exists()) {
                throw new ExtensionInstallationException('package_already_installed', "Extension \"{$manifest->id}\" is already installed.", ['id' => $manifest->id]);
            }

            $relativePath = $this->basePath.'/'.$manifest->id;
            $targetPath = Storage::disk($this->disk)->path($relativePath);

            if (! is_dir($targetPath) && ! mkdir($targetPath, 0755, true) && ! is_dir($targetPath)) {
                throw new ExtensionInstallationException('package_storage_unwritable', "Could not create the extension's destination directory.");
            }

            try {
                $validator->extract($zip, $targetPath);
            } catch (PackageValidationException $exception) {
                throw new ExtensionInstallationException($exception->errorCode, $exception->getMessage(), $exception->params);
            }

            return Extension::query()->create([
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'type' => $manifest->type,
                'status' => ExtensionStatus::Disabled->value,
                'manifest' => json_decode($manifestJson, true),
                'package_sha256' => hash_file('sha256', $uploadedZipPath) ?: null,
            ]);
        } finally {
            $zip->close();
        }
    }
}
