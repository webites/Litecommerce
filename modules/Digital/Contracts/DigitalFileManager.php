<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

/**
 * Panel-side management of a digital variant's file slots, consumed from
 * Catalog\Presentation\Controllers\ProductController the same way
 * Pricing\Contracts\VariantPriceManager already is — file management
 * belongs to the variant edit form, not a cross-product screen.
 */
interface DigitalFileManager
{
    public function createFile(
        int $variantId,
        string $label,
        ?int $maxDownloads,
        ?int $validityDays,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId,
    ): DigitalFileData;

    /**
     * Uploads a new version onto an existing file slot without touching
     * grants already issued against the previous version.
     */
    public function uploadNewVersion(
        int $fileId,
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId,
    ): DigitalFileData;

    public function updateSettings(int $fileId, string $label, ?int $maxDownloads, ?int $validityDays): void;

    /**
     * Deactivating stops the slot from being granted to future purchases;
     * it never revokes rights already granted — "Archiwizacja nie odbiera
     * praw" applies here the same as to product archiving.
     */
    public function setActive(int $fileId, bool $active): void;

    /** @param  list<int>  $orderedFileIds */
    public function reorder(int $variantId, array $orderedFileIds): void;
}
