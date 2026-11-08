<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;
use Litecommerce\Modules\Media\Infrastructure\MediaDataMapper;
use Litecommerce\Modules\Media\Infrastructure\Models\Media;
use Litecommerce\Modules\Media\Infrastructure\Models\MediaAttachment;

final class EloquentMediaAttachments implements MediaAttachments
{
    public function attach(string $ownerType, int $ownerId, string $collection, int $mediaId): void
    {
        $nextPosition = 1 + (int) MediaAttachment::query()
            ->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('collection', $collection)
            ->max('position');

        MediaAttachment::query()->firstOrCreate(
            ['media_id' => $mediaId, 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'collection' => $collection],
            ['position' => $nextPosition],
        );
    }

    public function detach(string $ownerType, int $ownerId, string $collection, int $mediaId): void
    {
        MediaAttachment::query()
            ->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('collection', $collection)
            ->where('media_id', $mediaId)->delete();
    }

    public function reorder(string $ownerType, int $ownerId, string $collection, array $orderedMediaIds): void
    {
        DB::transaction(function () use ($ownerType, $ownerId, $collection, $orderedMediaIds): void {
            foreach ($orderedMediaIds as $position => $mediaId) {
                MediaAttachment::query()
                    ->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('collection', $collection)
                    ->where('media_id', $mediaId)->update(['position' => $position + 1]);
            }
        });
    }

    public function list(string $ownerType, int $ownerId, string $collection): array
    {
        return $this->listMany($ownerType, [$ownerId], $collection)[$ownerId] ?? [];
    }

    public function listMany(string $ownerType, array $ownerIds, string $collection): array
    {
        $ownerIds = array_values(array_unique(array_map('intval', $ownerIds)));

        if ($ownerIds === []) {
            return [];
        }

        $attachments = MediaAttachment::query()
            ->where('owner_type', $ownerType)->whereIn('owner_id', $ownerIds)->where('collection', $collection)
            ->orderBy('owner_id')->orderBy('position')->orderBy('id')->get();

        $mediaById = Media::query()->whereIn('id', $attachments->pluck('media_id'))->get()->keyBy('id');
        $result = [];

        foreach ($attachments as $attachment) {
            $media = $mediaById->get($attachment->media_id);

            if ($media !== null) {
                $result[$attachment->owner_id][] = MediaDataMapper::toData($media);
            }
        }

        return $result;
    }
}
