<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Digital\Contracts\DigitalFileData;
use Litecommerce\Modules\Digital\Contracts\DigitalFileManager;
use Litecommerce\Modules\Digital\Infrastructure\DigitalDataMapper;
use Litecommerce\Modules\Digital\Infrastructure\Models\DigitalAssetVersion;
use Litecommerce\Modules\Digital\Infrastructure\Models\VariantFileSet;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;

final class ManageDigitalFiles implements DigitalFileManager
{
    public function __construct(private readonly PrivateFileLibrary $files) {}

    public function createFile(
        int $variantId,
        string $label,
        ?int $maxDownloads,
        ?int $validityDays,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId,
    ): DigitalFileData {
        return DB::transaction(function () use ($variantId, $label, $maxDownloads, $validityDays, $sourcePath, $originalFilename, $mimeType, $uploadedByStaffId): DigitalFileData {
            $position = 1 + (int) VariantFileSet::query()->where('variant_id', $variantId)->max('position');
            $fileSet = VariantFileSet::query()->create([
                'variant_id' => $variantId, 'label' => $label, 'active' => true,
                'max_downloads' => $maxDownloads, 'validity_days' => $validityDays, 'position' => $position,
            ]);
            $version = $this->storeVersion($fileSet, $sourcePath, $originalFilename, $mimeType, $uploadedByStaffId);
            $fileSet->update(['current_version_id' => $version->getKey()]);

            return DigitalDataMapper::toFileData($fileSet->setRelation('currentVersion', $version));
        });
    }

    public function uploadNewVersion(
        int $fileId,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId,
    ): DigitalFileData {
        $fileSet = VariantFileSet::query()->findOrFail($fileId);
        $version = $this->storeVersion($fileSet, $sourcePath, $originalFilename, $mimeType, $uploadedByStaffId);
        $fileSet->update(['current_version_id' => $version->getKey()]);

        return DigitalDataMapper::toFileData($fileSet->setRelation('currentVersion', $version));
    }

    public function updateSettings(int $fileId, string $label, ?int $maxDownloads, ?int $validityDays): void
    {
        VariantFileSet::query()->whereKey($fileId)->update([
            'label' => $label, 'max_downloads' => $maxDownloads, 'validity_days' => $validityDays,
        ]);
    }

    public function setActive(int $fileId, bool $active): void
    {
        VariantFileSet::query()->whereKey($fileId)->update(['active' => $active]);
    }

    public function reorder(int $variantId, array $orderedFileIds): void
    {
        DB::transaction(function () use ($variantId, $orderedFileIds): void {
            foreach ($orderedFileIds as $position => $fileId) {
                VariantFileSet::query()->where('variant_id', $variantId)->whereKey($fileId)->update(['position' => $position + 1]);
            }
        });
    }

    private function storeVersion(
        VariantFileSet $fileSet,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId,
    ): DigitalAssetVersion {
        $stored = $this->files->storeFromPath($sourcePath, $originalFilename, $mimeType, $uploadedByStaffId);
        $nextVersionNumber = 1 + (int) DigitalAssetVersion::query()->where('variant_file_set_id', $fileSet->getKey())->max('version_number');

        return DigitalAssetVersion::query()->create([
            'variant_file_set_id' => $fileSet->getKey(), 'version_number' => $nextVersionNumber,
            'private_file_id' => $stored->id, 'original_filename' => $stored->originalFilename,
            'size_bytes' => $stored->sizeBytes, 'uploaded_by' => $uploadedByStaffId,
        ]);
    }
}
