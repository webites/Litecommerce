<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;

/**
 * Replaces application files from a staging directory. Every change is first copied to the file backup and
 * written to a journal (persisted per batch before the batch is applied), so an interrupted or failed run can be undone.
 */
final class FileSwapper
{
    private const BATCH = 100;

    /** @throws UpdateStepFailedException */
    public function swap(UpdatePaths $paths, UpdateOperationState $state, ReleaseManifest $new, ?ReleaseManifest $old, UpdateProgress $progress): void
    {
        $staging = $paths->staging($state->id);
        $backup = $paths->fileBackup($state->id);

        $replace = array_values(array_filter(
            [...array_keys($new->files), BuildReleasePackage::MANIFEST],
            static fn (string $path): bool => ! $paths->isProtected($path),
        ));
        $delete = $old === null ? [] : array_values(array_filter(
            array_diff(array_keys($old->files), array_keys($new->files)),
            static fn (string $path): bool => ! $paths->isProtected($path),
        ));

        foreach (array_chunk($replace, self::BATCH) as $batch) {
            $todo = array_values(array_filter($batch, fn (string $path): bool => ! $this->alreadyCurrent($paths->root.'/'.$path, $staging.'/'.$path)));
            $this->record($paths, $state, $backup, $todo);
            $progress->persist($state);

            foreach ($todo as $path) {
                $this->install($staging.'/'.$path, $paths->root.'/'.$path);
            }
        }

        foreach (array_chunk($delete, self::BATCH) as $batch) {
            $this->record($paths, $state, $backup, $batch);
            $progress->persist($state);

            foreach ($batch as $path) {
                @unlink($paths->root.'/'.$path);
            }
        }
    }

    /** Puts every journaled file back; safe to run repeatedly. */
    public function restore(UpdatePaths $paths, UpdateOperationState $state): void
    {
        $backup = $paths->fileBackup($state->id);

        foreach ($state->journal as $path => $original) {
            $target = $paths->root.'/'.$path;

            if ($original === 'absent') {
                @unlink($target);

                continue;
            }

            if (! is_file($backup.'/'.$path)) {
                throw new UpdateStepFailedException('restore_failed', "The backup copy is missing: {$path}");
            }

            $this->install($backup.'/'.$path, $target);
        }
    }

    /** @param list<string> $paths */
    private function record(UpdatePaths $context, UpdateOperationState $state, string $backup, array $paths): void
    {
        foreach ($paths as $path) {
            if (isset($state->journal[$path])) {
                continue;
            }

            $current = $context->root.'/'.$path;

            if (! is_file($current)) {
                $state->journal[$path] = 'absent';

                continue;
            }

            $copy = $backup.'/'.$path;
            if (! is_dir(dirname($copy)) && ! @mkdir(dirname($copy), 0775, true) && ! is_dir(dirname($copy))) {
                throw new UpdateStepFailedException('backup_unwritable');
            }
            if (! @copy($current, $copy)) {
                throw new UpdateStepFailedException('backup_unwritable', "Cannot back up {$path}");
            }

            $state->journal[$path] = 'present';
        }
    }

    private function alreadyCurrent(string $target, string $source): bool
    {
        return is_file($target) && hash_file('sha256', $target) === hash_file('sha256', $source);
    }

    private function install(string $source, string $target): void
    {
        if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
            throw new UpdateStepFailedException('swap_failed', 'Cannot create '.dirname($target));
        }

        $temporary = $target.'.lcnew';
        if (! @copy($source, $temporary) || ! @rename($temporary, $target)) {
            @unlink($temporary);

            throw new UpdateStepFailedException('swap_failed', "Cannot write {$target}");
        }
    }
}
