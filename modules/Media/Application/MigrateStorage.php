<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Application;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Media\Infrastructure\Models\Media;
use Litecommerce\Modules\Media\Infrastructure\Models\PrivateFile;

/**
 * Copies files to another disk in batches, verifies each copy, then switches the per-file disk.
 * Source copies are kept unless deleteSource is set. Safe to re-run: only files not yet on the target are touched.
 */
final class MigrateStorage
{
    /** @return array{migrated: int, failed: int, remaining: int} */
    public function run(string $type, string $targetDisk, int $batchSize, bool $dryRun = false, bool $deleteSource = false): array
    {
        $this->assertWritable($targetDisk);

        $model = $type === 'private' ? PrivateFile::class : Media::class;
        $migrated = 0;
        $failed = 0;

        $files = $model::query()->where('disk', '!=', $targetDisk)->orderBy('id')->limit(max(1, $batchSize))->get();

        foreach ($files as $file) {
            if ($dryRun) {
                $migrated++;

                continue;
            }

            try {
                $this->migrateOne($file, $targetDisk, $deleteSource);
                $migrated++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::error('Storage migration of a file failed.', ['type' => $type, 'file_id' => $file->getKey(), 'error_code' => $exception::class]);
            }
        }

        return ['migrated' => $migrated, 'failed' => $failed, 'remaining' => $model::query()->where('disk', '!=', $targetDisk)->count() - ($dryRun ? $migrated : 0)];
    }

    private function assertWritable(string $disk): void
    {
        $probe = '.migration-probe-'.bin2hex(random_bytes(4));
        $storage = Storage::disk($disk);

        if ($storage->put($probe, 'probe') === false || $storage->get($probe) !== 'probe') {
            throw new \RuntimeException("Disk [{$disk}] is not writable.");
        }
        $storage->delete($probe);
    }

    private function migrateOne(Media|PrivateFile $file, string $targetDisk, bool $deleteSource): void
    {
        $sourceDisk = $file->disk;
        $paths = [$file->path];
        if ($file instanceof Media) {
            $paths = [...$paths, ...array_values($file->variants ?? [])];
        }

        foreach ($paths as $path) {
            $this->copy($sourceDisk, $targetDisk, $path);
        }

        if ($file instanceof PrivateFile && $this->hash($targetDisk, $file->path) !== $file->checksum_sha256) {
            Storage::disk($targetDisk)->delete($file->path);
            throw new \RuntimeException('Checksum mismatch after copy.');
        }

        $file->update(['disk' => $targetDisk]);

        if ($deleteSource) {
            foreach ($paths as $path) {
                Storage::disk($sourceDisk)->delete($path);
            }
        }
    }

    private function copy(string $from, string $to, string $path): void
    {
        $stream = Storage::disk($from)->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException("Source file [{$path}] is unreadable.");
        }

        try {
            if (! Storage::disk($to)->writeStream($path, $stream)) {
                throw new \RuntimeException("Target write of [{$path}] failed.");
            }
        } finally {
            fclose($stream);
        }

        if (Storage::disk($to)->size($path) !== Storage::disk($from)->size($path)) {
            Storage::disk($to)->delete($path);
            throw new \RuntimeException("Size mismatch after copying [{$path}].");
        }
    }

    private function hash(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            return '';
        }
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
