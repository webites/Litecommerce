<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Application;

/** Keeps the newest N archives made by this tool; the timestamp in the file name defines the order. */
final class BackupRetention
{
    public const PREFIX = 'litecommerce-backup-';

    public function prune(string $directory, int $keep): int
    {
        $archives = glob($directory.'/'.self::PREFIX.'*.zip') ?: [];
        rsort($archives);
        $removed = 0;

        foreach (array_slice($archives, max(1, $keep)) as $archive) {
            if (@unlink($archive)) {
                @unlink($archive.'.sha256');
                $removed++;
            }
        }

        return $removed;
    }
}
