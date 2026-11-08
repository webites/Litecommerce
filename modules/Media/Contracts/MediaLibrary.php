<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

/**
 * Owns uploaded files and the storage disk they live on. The disk is
 * configured (config/media.php), not chosen by callers, so swapping the
 * storage provider never touches consuming modules.
 */
interface MediaLibrary
{
    public function store(
        string $contents,
        string $originalFilename,
        string $mimeType,
        ?string $altText = null,
        ?string $uploadedByStaffId = null,
    ): MediaData;

    public function find(int $mediaId): ?MediaData;

    public function delete(int $mediaId): void;

    /**
     * @param  list<int>  $mediaIds
     * @return array<int, MediaData> keyed by media id
     */
    public function findMany(array $mediaIds): array;

    /**
     * Newest-first page of raster/vector images, cursor-paginated by media id.
     *
     * @return list<MediaData>
     */
    public function listImages(int $limit, ?int $beforeId = null, ?string $search = null): array;
}
