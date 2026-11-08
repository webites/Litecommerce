<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Media\Infrastructure\ImageThumbnailer;
use Litecommerce\Modules\Media\Infrastructure\MediaDataMapper;
use Litecommerce\Modules\Media\Infrastructure\Models\Media;

final class EloquentMediaLibrary implements MediaLibrary
{
    public function __construct(private readonly ImageThumbnailer $thumbnailer) {}

    public function store(
        string $contents,
        string $originalFilename,
        string $mimeType,
        ?string $altText = null,
        ?string $uploadedByStaffId = null,
    ): MediaData {
        $disk = (string) config('media.disk', 'public');
        $extension = Str::lower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $path = 'catalog/'.date('Y/m').'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        Storage::disk($disk)->put($path, $contents);
        $this->mirrorForFallbackServing($disk, $path);

        [$width, $height, $variantPaths] = $this->storeThumbnails($disk, $path, $contents, $mimeType);

        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->afterRollBack(fn (): bool => $this->deleteFile($disk, $path, $variantPaths));
        }

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
            'variants' => $variantPaths === [] ? null : $variantPaths,
            'alt_text' => $altText,
            'uploaded_by' => $uploadedByStaffId,
        ]);

        return $this->toData($media);
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: array<int, string>} [width, height, width => stored variant path]
     */
    private function storeThumbnails(string $disk, string $originalPath, string $contents, string $mimeType): array
    {
        $processed = $this->thumbnailer->process($contents, $mimeType);

        if ($processed === null) {
            return [null, null, []];
        }

        [$width, $height, $variants] = $processed;
        $extension = Str::lower(pathinfo($originalPath, PATHINFO_EXTENSION));
        $basePath = $extension !== '' ? Str::beforeLast($originalPath, '.'.$extension) : $originalPath;
        $variantPaths = [];

        foreach ($variants as $variantWidth => $variantContents) {
            $variantPath = $basePath.'-'.$variantWidth.'w'.($extension !== '' ? '.'.$extension : '');
            Storage::disk($disk)->put($variantPath, $variantContents);
            $this->mirrorForFallbackServing($disk, $variantPath);
            $variantPaths[$variantWidth] = $variantPath;
        }

        return [$width, $height, $variantPaths];
    }

    public function find(int $mediaId): ?MediaData
    {
        $media = Media::query()->find($mediaId);

        return $media === null ? null : $this->toData($media);
    }

    public function delete(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);
        if ($media === null) {
            return;
        }

        $disk = $media->disk;
        $path = $media->path;
        $variantPaths = $media->variants ?? [];
        $media->delete();

        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->afterCommit(fn (): bool => $this->deleteFile($disk, $path, $variantPaths));

            return;
        }

        $this->deleteFile($disk, $path, $variantPaths);
    }

    public function listImages(int $limit, ?int $beforeId = null, ?string $search = null): array
    {
        $query = Media::query()->where('mime_type', 'like', 'image/%')->orderByDesc('id')->limit(max(1, min($limit, 100)));
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }
        if ($search !== null && trim($search) !== '') {
            $query->where('original_filename', 'like', '%'.addcslashes(trim($search), '\\%_').'%');
        }

        return $query->get()->map(fn (Media $media): MediaData => $this->toData($media))->values()->all();
    }

    public function findMany(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return [];
        }

        return Media::query()->whereIn('id', $mediaIds)->get()
            ->mapWithKeys(fn (Media $media): array => [(int) $media->getKey() => $this->toData($media)])
            ->all();
    }

    /**
     * Shared hosts sometimes disable symlink(), so PublicStorageLinker
     * falls back to a one-time directory copy at install time. When that
     * fallback is active, every later upload must also be mirrored —
     * otherwise only files that existed at install time would be servable.
     */
    private function mirrorForFallbackServing(string $disk, string $path): void
    {
        if ($disk !== 'public') {
            return;
        }

        $link = public_path('storage');

        if (! is_dir($link) || is_link($link)) {
            return;
        }

        $destination = $link.DIRECTORY_SEPARATOR.$path;
        File::ensureDirectoryExists(dirname($destination));
        File::copy(Storage::disk('public')->path($path), $destination);
    }

    /** @param array<int, string> $variantPaths */
    private function deleteFile(string $disk, string $path, array $variantPaths = []): bool
    {
        $deleted = Storage::disk($disk)->delete($path);

        if ($disk === 'public') {
            $link = public_path('storage');
            if (is_dir($link) && ! is_link($link)) {
                File::delete($link.DIRECTORY_SEPARATOR.$path);
            }
        }

        foreach ($variantPaths as $variantPath) {
            Storage::disk($disk)->delete($variantPath);

            if ($disk === 'public') {
                $link = public_path('storage');
                if (is_dir($link) && ! is_link($link)) {
                    File::delete($link.DIRECTORY_SEPARATOR.$variantPath);
                }
            }
        }

        return $deleted;
    }

    private function toData(Media $media): MediaData
    {
        return MediaDataMapper::toData($media);
    }
}
