<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Backup\Contracts\BackupVerifier;

final class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify {archive : Path to a backup ZIP archive}';

    protected $description = 'Check a backup archive: manifest, declared entries and checksums';

    public function handle(BackupVerifier $verifier): int
    {
        $result = $verifier->verify((string) $this->argument('archive'));

        if (! $result->valid) {
            foreach ($result->problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        $this->components->info('The archive is intact (created '.($result->manifest['created_at'] ?? 'unknown').', targets: '.implode(', ', $result->manifest['targets'] ?? []).').');

        return self::SUCCESS;
    }
}
