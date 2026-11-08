<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

/**
 * Orders media in a named collection against an owner from another
 * module (e.g. Catalog products/variants) without that module depending
 * on Media's Eloquent models — owners are addressed by type + id only.
 */
interface MediaAttachments
{
    public function attach(string $ownerType, int $ownerId, string $collection, int $mediaId): void;

    public function detach(string $ownerType, int $ownerId, string $collection, int $mediaId): void;

    /** @param list<int> $orderedMediaIds */
    public function reorder(string $ownerType, int $ownerId, string $collection, array $orderedMediaIds): void;

    /** @return list<MediaData> ordered by position */
    public function list(string $ownerType, int $ownerId, string $collection): array;

    /**
     * @param  list<int>  $ownerIds
     * @return array<int, list<MediaData>> keyed by owner id; owners without media are omitted
     */
    public function listMany(string $ownerType, array $ownerIds, string $collection): array;
}
