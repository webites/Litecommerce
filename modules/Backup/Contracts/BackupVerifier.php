<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

interface BackupVerifier
{
    /** Reads the whole archive and checks the manifest, the declared entries and every entry's checksum. */
    public function verify(string $archivePath): BackupVerification;
}
