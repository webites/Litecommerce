<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Repositories;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Litecommerce\Modules\Media\Contracts\PrivateFileData;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;
use Litecommerce\Modules\Media\Infrastructure\Models\PrivateFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class EloquentPrivateFileLibrary implements PrivateFileLibrary
{
    public function store(
        string $contents,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId = null,
    ): PrivateFileData {
        $disk = (string) config('media.private_disk', 'local');
        $path = $this->newPath($originalFilename);

        Storage::disk($disk)->put($path, $contents);

        return $this->record($disk, $path, $originalFilename, $mimeType, strlen($contents), hash('sha256', $contents), $uploadedByStaffId);
    }

    public function storeFromPath(
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId = null,
    ): PrivateFileData {
        $disk = (string) config('media.private_disk', 'local');
        $path = $this->newPath($originalFilename);
        $checksum = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);
        if ($checksum === false || $size === false) {
            throw new \RuntimeException('The source file could not be read.');
        }

        if (Storage::disk($disk)->putFileAs(dirname($path), new File($sourcePath), basename($path)) === false) {
            throw new \RuntimeException('The file could not be written to private storage.');
        }

        return $this->record($disk, $path, $originalFilename, $mimeType, $size, $checksum, $uploadedByStaffId);
    }

    public function find(int $fileId): ?PrivateFileData
    {
        $file = PrivateFile::query()->find($fileId);

        return $file === null ? null : $this->toData($file);
    }

    public function isServable(int $fileId): bool
    {
        $status = PrivateFile::query()->whereKey($fileId)->value('integrity_status');

        return $status === null || $status === 'ok';
    }

    public function respond(int $fileId, string $downloadFilename): Response
    {
        $file = PrivateFile::query()->findOrFail($fileId);
        $storage = Storage::disk($file->disk);

        if (! $this->isLocal($file->disk)) {
            if ($storage->providesTemporaryUrls()) {
                return new RedirectResponse($storage->temporaryUrl($file->path, now()->addMinutes(5), [
                    'ResponseContentDisposition' => HeaderUtils::makeDisposition('attachment', $downloadFilename, Str::ascii($downloadFilename)),
                    'ResponseContentType' => $file->mime_type,
                ]), 302, ['Cache-Control' => 'no-store']);
            }

            return $storage->download($file->path, $downloadFilename, ['Content-Type' => $file->mime_type, 'Cache-Control' => 'no-store']);
        }

        $fullPath = $storage->path($file->path);
        $offload = $this->offloadHeaders($file, $fullPath);
        if ($offload !== null) {
            return new Response('', 200, [
                ...$offload,
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $downloadFilename, Str::ascii($downloadFilename)),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        $response = new BinaryFileResponse($fullPath, 200, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ], true, 'attachment');
        $response->setContentDisposition('attachment', $downloadFilename);
        $response->prepare(request());

        return $response;
    }

    private function isLocal(string $disk): bool
    {
        return (config("filesystems.disks.{$disk}.driver") ?? null) === 'local';
    }

    /** @return array<string, string>|null */
    private function offloadHeaders(PrivateFile $file, string $fullPath): ?array
    {
        return match ((string) config('media.download_offload', 'none')) {
            'x-sendfile' => ['X-Sendfile' => $fullPath],
            'litespeed' => ['X-LiteSpeed-Location' => $fullPath],
            'x-accel-redirect' => ['X-Accel-Redirect' => rtrim((string) config('media.download_offload_prefix', '/protected-private'), '/').'/'.ltrim($file->path, '/')],
            default => null,
        };
    }

    private function newPath(string $originalFilename): string
    {
        $extension = Str::lower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        return 'digital/'.date('Y/m').'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
    }

    private function record(string $disk, string $path, string $originalFilename, string $mimeType, int $size, string $checksum, ?string $uploadedByStaffId): PrivateFileData
    {
        return $this->toData(PrivateFile::query()->create([
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'integrity_status' => 'ok',
            'verified_at' => now(),
            'uploaded_by' => $uploadedByStaffId,
        ]));
    }

    private function toData(PrivateFile $file): PrivateFileData
    {
        return new PrivateFileData(
            id: (int) $file->getKey(),
            originalFilename: $file->original_filename,
            mimeType: $file->mime_type,
            sizeBytes: $file->size_bytes,
            checksumSha256: $file->checksum_sha256,
            integrityStatus: $file->integrity_status,
        );
    }
}
