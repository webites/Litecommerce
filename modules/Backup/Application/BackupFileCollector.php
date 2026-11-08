<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Application;

use Litecommerce\Modules\Backup\Contracts\BackupFailedException;
use Litecommerce\Modules\Backup\Contracts\BackupTarget;

/** Lists the regular files to archive; symlinks are never followed, so a link cannot pull in files from outside the roots. */
final class BackupFileCollector
{
    /**
     * @param  list<BackupTarget>  $targets
     * @param  list<string>  $extraPaths
     * @param  list<string>  $excludes  globs relative to the application root
     * @return array{files: list<array{path: string, entry: string, size: int}>, skipped: int}
     */
    public function collect(array $targets, array $extraPaths, array $excludes, string $outputDirectory): array
    {
        $files = [];
        $skipped = 0;

        foreach ($this->roots($targets, $extraPaths) as $entryPrefix => $root) {
            foreach ($this->walk($root) as $path) {
                if ($this->isExcluded($path, $excludes, $outputDirectory)) {
                    continue;
                }
                if (! is_readable($path)) {
                    $skipped++;

                    continue;
                }

                $relative = is_dir($root) ? substr($path, strlen($root) + 1) : basename($path);
                $files[] = ['path' => $path, 'entry' => rtrim($entryPrefix, '/').'/'.ltrim(str_replace('\\', '/', $relative), '/'), 'size' => (int) filesize($path)];
            }
        }

        return ['files' => $files, 'skipped' => $skipped];
    }

    /**
     * @param  list<BackupTarget>  $targets
     * @param  list<string>  $extraPaths
     * @return array<string, string> archive prefix => absolute file or directory
     */
    private function roots(array $targets, array $extraPaths): array
    {
        $roots = [];
        $candidates = [
            BackupTarget::PublicFiles->value => ['files/public', (string) config('filesystems.disks.public.root')],
            BackupTarget::PrivateFiles->value => ['files/private', (string) config('filesystems.disks.local.root')],
            BackupTarget::Env->value => ['files', base_path('.env')],
        ];

        foreach ($targets as $target) {
            if (! isset($candidates[$target->value])) {
                continue;
            }
            [$prefix, $path] = $candidates[$target->value];
            $real = realpath($path);
            if ($real !== false) {
                $roots[$prefix] = $real;
            }
        }

        $base = realpath(base_path());
        foreach ($extraPaths as $extra) {
            $real = realpath(str_starts_with($extra, '/') ? $extra : base_path($extra));
            if ($real === false || $base === false || ($real !== $base && ! str_starts_with($real, $base.DIRECTORY_SEPARATOR))) {
                throw new BackupFailedException("Extra path '{$extra}' does not exist or is outside the application directory.");
            }
            $roots['files/custom/'.ltrim(substr($real, strlen($base)), '/')] = $real;
        }

        return $roots;
    }

    /** @return \Generator<string> */
    private function walk(string $root): \Generator
    {
        if (is_file($root) && ! is_link($root)) {
            yield $root;

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && ! $item->isLink()) {
                yield $item->getPathname();
            }
        }
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $path, array $excludes, string $outputDirectory): bool
    {
        if (str_ends_with($path, '.partial') || str_starts_with($path, $outputDirectory.DIRECTORY_SEPARATOR)) {
            return true;
        }

        $base = (string) realpath(base_path());
        $relative = str_starts_with($path, $base.DIRECTORY_SEPARATOR) ? substr($path, strlen($base) + 1) : $path;

        return array_any($excludes, static fn (string $pattern): bool => fnmatch($pattern, $relative));
    }
}
