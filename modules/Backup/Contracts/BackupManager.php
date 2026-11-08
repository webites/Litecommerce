<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

/** Best-effort backup of the database and selected files into one ZIP archive; restoring stays a manual procedure. */
interface BackupManager
{
    /** @throws BackupFailedException when nothing usable was produced; a partial archive is never left behind */
    public function create(BackupRequest $request): BackupResult;
}
