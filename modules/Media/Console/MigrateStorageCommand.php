<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Media\Application\MigrateStorage;

final class MigrateStorageCommand extends Command
{
    protected $signature = 'media:migrate-disk {type : public or private} {disk : Target disk name} {--batch=100 : Files per run} {--dry-run : Only count what would be copied} {--delete-source : Remove source copies after each verified copy}';

    protected $description = 'Copy stored files to another disk in verified batches and switch their location';

    public function handle(MigrateStorage $migrator): int
    {
        $type = (string) $this->argument('type');
        if (! in_array($type, ['public', 'private'], true)) {
            $this->error('Type must be "public" or "private".');

            return self::INVALID;
        }

        try {
            $result = $migrator->run($type, (string) $this->argument('disk'), (int) $this->option('batch'), (bool) $this->option('dry-run'), (bool) $this->option('delete-source'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Migrated: {$result['migrated']}, failed: {$result['failed']}, remaining: {$result['remaining']}.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
