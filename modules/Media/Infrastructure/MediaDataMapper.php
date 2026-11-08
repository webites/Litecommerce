<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Infrastructure\Models\Media;
use Throwable;

final class MediaDataMapper
{
    public static function toData(Media $media): MediaData
    {
        $url = null;
        $variants = [];

        try {
            $url = Storage::disk($media->disk)->url($media->path);

            foreach ($media->variants ?? [] as $width => $path) {
                $variants[(int) $width] = Storage::disk($media->disk)->url($path);
            }
        } catch (Throwable) {
            // Disk has no public URL (e.g. a private disk) — leave url null/variants empty.
        }

        return new MediaData(
            id: (int) $media->getKey(),
            path: $media->path,
            url: $url,
            originalFilename: $media->original_filename,
            mimeType: $media->mime_type,
            sizeBytes: $media->size_bytes,
            width: $media->width,
            height: $media->height,
            variants: $variants,
            altText: $media->alt_text,
        );
    }
}
