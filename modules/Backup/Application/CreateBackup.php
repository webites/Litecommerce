<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Application;

use Litecommerce\Modules\Backup\Contracts\BackupFailedException;
use Litecommerce\Modules\Backup\Contracts\BackupManager;
use Litecommerce\Modules\Backup\Contracts\BackupRequest;
use Litecommerce\Modules\Backup\Contracts\BackupResult;
use Litecommerce\Modules\Backup\Contracts\BackupTarget;
use Litecommerce\Modules\Backup\Infrastructure\DatabaseDumperFactory;

final class CreateBackup implements BackupManager
{
    public const FORMAT_VERSION = 1;

    private const STORED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'zip', 'gz', 'mp4', 'webm', 'mp3', 'pdf', '7z', 'rar'];

    public function __construct(
        private readonly DatabaseDumperFactory $dumpers,
        private readonly BackupFileCollector $collector,
        private readonly BackupRetention $retention,
    ) {}

    public function create(BackupRequest $request): BackupResult
    {
        if ($request->targets === [] && $request->extraPaths === []) {
            throw new BackupFailedException('Nothing to back up: choose at least one target or path.');
        }
        if ($request->keep !== null && $request->keep < 1) {
            throw new BackupFailedException('--keep must be at least 1.');
        }

        @set_time_limit(0);
        $directory = $this->prepareDirectory($request->outputDirectory ?? (string) config('backup.directory'));
        $lock = $this->lock($directory);
        $createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $final = $directory.'/'.BackupRetention::PREFIX.$createdAt->format('Ymd-His').$this->suffix($request->label).'.zip';
        $partial = $final.'.partial';
        $dumpFile = null;

        try {
            ['files' => $files, 'skipped' => $skipped] = $this->collector->collect($request->targets, $request->extraPaths, $request->excludes, $directory);
            $this->assertFreeSpace($directory, array_sum(array_column($files, 'size')));

            $dumper = null;
            if (in_array(BackupTarget::Database, $request->targets, true)) {
                $dumper = $this->dumpers->make($request->databaseMethod);
                $dumpFile = $directory.'/.dump-'.bin2hex(random_bytes(6)).'.tmp';
                $dumper->dumpTo($dumpFile);
            }

            $warnings = $this->warnings($request, $skipped);
            $this->writeArchive($partial, $createdAt, $request, $files, $dumper?->entryName(), $dumpFile, $dumper?->method());

            if (! rename($partial, $final)) {
                throw new BackupFailedException('Cannot finalize the backup archive.');
            }
            $sha256 = (string) hash_file('sha256', $final);
            file_put_contents($final.'.sha256', $sha256.'  '.basename($final)."\n");
            $removed = $request->keep === null ? 0 : $this->retention->prune($directory, $request->keep);

            return new BackupResult($final, (int) filesize($final), $sha256, $createdAt, $request->targets, count($files), $dumper?->method(), $warnings, $removed);
        } catch (\Throwable $exception) {
            @unlink($partial);
            throw $exception instanceof BackupFailedException ? $exception : new BackupFailedException('Backup failed: '.$exception->getMessage(), 0, $exception);
        } finally {
            if ($dumpFile !== null) {
                @unlink($dumpFile);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param  list<array{path: string, entry: string, size: int}>  $files
     */
    private function writeArchive(string $partial, \DateTimeImmutable $createdAt, BackupRequest $request, array $files, ?string $dumpEntry, ?string $dumpFile, ?string $dbMethod): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($partial, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new BackupFailedException('Cannot create the backup archive.');
        }

        if ($dumpEntry !== null && $dumpFile !== null) {
            $zip->addFile($dumpFile, $dumpEntry);
        }

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['entry']);
            if (in_array(strtolower(pathinfo($file['path'], PATHINFO_EXTENSION)), self::STORED_EXTENSIONS, true)) {
                $zip->setCompressionName($file['entry'], \ZipArchive::CM_STORE);
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'format_version' => self::FORMAT_VERSION,
            'created_at' => $createdAt->format(\DATE_ATOM),
            'application_version' => (string) config('extensions.core_version'),
            'php_version' => PHP_VERSION,
            'targets' => array_map(static fn (BackupTarget $target): string => $target->value, $request->targets),
            'database' => $dumpEntry === null ? null : ['entry' => $dumpEntry, 'method' => $dbMethod],
            'files' => ['count' => count($files), 'bytes' => array_sum(array_column($files, 'size'))],
            'extra_paths' => $request->extraPaths,
            'excludes' => $request->excludes,
            'label' => $request->label,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $zip->close()) {
            throw new BackupFailedException('Writing the backup archive failed (disk full?).');
        }
    }

    /** @return list<string> */
    private function warnings(BackupRequest $request, int $skipped): array
    {
        $warnings = [];
        if (in_array(BackupTarget::Database, $request->targets, true) && ! in_array(BackupTarget::Env, $request->targets, true)) {
            $warnings[] = 'The archive has no .env. Encrypted database columns can be read only with the same APP_KEY, so keep it somewhere safe or add the env target.';
        }
        if (in_array(BackupTarget::Env, $request->targets, true)) {
            $warnings[] = 'The archive contains .env with secrets (database password, APP_KEY); store it as carefully as the credentials themselves.';
        }
        if ($skipped > 0) {
            $warnings[] = "{$skipped} unreadable file(s) were skipped.";
        }

        return $warnings;
    }

    private function prepareDirectory(string $directory): string
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new BackupFailedException("Cannot create the backup directory '{$directory}'.");
        }
        $real = realpath($directory);
        if ($real === false || ! is_writable($real)) {
            throw new BackupFailedException("The backup directory '{$directory}' is not writable.");
        }

        foreach ([config('filesystems.disks.public.root'), public_path()] as $webRoot) {
            $webReal = is_string($webRoot) ? realpath($webRoot) : false;
            if ($webReal !== false && ($real === $webReal || str_starts_with($real, $webReal.DIRECTORY_SEPARATOR))) {
                throw new BackupFailedException('The backup directory must not be inside a web-accessible location.');
            }
        }

        return $real;
    }

    /** @return resource */
    private function lock(string $directory)
    {
        $handle = fopen($directory.'/.backup.lock', 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new BackupFailedException('Another backup is already running.');
        }

        return $handle;
    }

    private function assertFreeSpace(string $directory, int $filesBytes): void
    {
        $free = @disk_free_space($directory);
        $needed = $filesBytes + (int) config('backup.min_free_space_mb', 10) * 1048576;
        if ($free !== false && $free < $needed) {
            throw new BackupFailedException('Not enough free disk space for the backup (need about '.round($needed / 1048576).' MB).');
        }
    }

    private function suffix(?string $label): string
    {
        $clean = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $label)), '-');

        return $clean === '' ? '' : '-'.substr($clean, 0, 40);
    }
}
