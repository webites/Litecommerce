<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Application;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Media\Infrastructure\Models\PrivateFile;

final class VerifyPrivateFiles
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Re-hashes the least recently verified files; returns how many were checked. */
    public function run(int $limit): int
    {
        $files = PrivateFile::query()
            ->orderByRaw('verified_at IS NULL DESC')
            ->orderBy('verified_at')
            ->limit(max(1, $limit))
            ->get();

        foreach ($files as $file) {
            $this->verify($file);
        }

        return $files->count();
    }

    public function verify(PrivateFile $file): string
    {
        $status = $this->status($file);
        $previous = $file->integrity_status;
        $file->update(['integrity_status' => $status, 'verified_at' => now()]);

        if ($status !== 'ok' && $previous === 'ok') {
            Log::error('Private file failed its integrity check.', ['private_file_id' => $file->getKey(), 'status' => $status]);
            $this->audit->record(new AuditEntryData('system', null, 'media.private_file.integrity_failed', 'private_file', (string) $file->getKey(), [], ['status' => $status]));
        }

        return $status;
    }

    private function status(PrivateFile $file): string
    {
        $disk = Storage::disk($file->disk);

        try {
            if (! $disk->exists($file->path)) {
                return 'missing';
            }
            $stream = $disk->readStream($file->path);
            if (! is_resource($stream)) {
                return 'missing';
            }
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            fclose($stream);

            return hash_equals($file->checksum_sha256, hash_final($context)) ? 'ok' : 'mismatch';
        } catch (\Throwable $exception) {
            Log::warning('Private file could not be read for verification.', ['private_file_id' => $file->getKey(), 'error_code' => $exception::class]);

            return $file->integrity_status;
        }
    }
}
