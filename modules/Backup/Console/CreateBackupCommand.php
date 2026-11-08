<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Backup\Contracts\BackupFailedException;
use Litecommerce\Modules\Backup\Contracts\BackupManager;
use Litecommerce\Modules\Backup\Contracts\BackupRequest;
use Litecommerce\Modules\Backup\Contracts\BackupTarget;

final class CreateBackupCommand extends Command
{
    protected $signature = 'backup:create
        {--only= : Comma-separated targets to back up instead of the default set (database,public,private,env)}
        {--without= : Comma-separated targets to leave out of the default set (database,public,private)}
        {--path=* : Extra file or directory inside the application directory (repeatable)}
        {--exclude=* : Glob relative to the application directory to leave out (repeatable)}
        {--output= : Target directory (default: BACKUP_DIRECTORY or storage/app/backups)}
        {--name= : Label appended to the archive name}
        {--db-method=auto : Database export method: auto, mysqldump or php}
        {--keep= : Keep only the newest N archives in the target directory}
        {--json : Print the result as JSON}';

    protected $description = 'Back up the database and selected files into one ZIP archive';

    public function handle(BackupManager $backups): int
    {
        try {
            $request = new BackupRequest(
                $this->targets(),
                array_values(array_filter(array_map('strval', (array) $this->option('path')), static fn (string $value): bool => $value !== '')),
                array_values(array_filter(array_map('strval', (array) $this->option('exclude')), static fn (string $value): bool => $value !== '')),
                $this->option('output') ?: null,
                $this->option('name') ?: null,
                (string) $this->option('db-method'),
                $this->option('keep') === null ? null : (int) $this->option('keep'),
            );
            $result = $backups->create($request);
        } catch (BackupFailedException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'path' => $result->path,
            'size_bytes' => $result->sizeBytes,
            'sha256' => $result->sha256,
            'created_at' => $result->createdAt->format(\DATE_ATOM),
            'targets' => array_map(static fn (BackupTarget $target): string => $target->value, $result->targets),
            'files' => $result->fileCount,
            'database_method' => $result->databaseMethod,
            'removed_old_backups' => $result->removedOldBackups,
            'warnings' => $result->warnings,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Backup created: '.$result->path);
        $this->components->twoColumnDetail('Size', number_format($result->sizeBytes / 1048576, 2).' MB');
        $this->components->twoColumnDetail('SHA-256', $result->sha256);
        $this->components->twoColumnDetail('Files', (string) $result->fileCount);
        $this->components->twoColumnDetail('Database', $result->databaseMethod ?? 'not included');
        if ($result->removedOldBackups > 0) {
            $this->components->twoColumnDetail('Old archives removed', (string) $result->removedOldBackups);
        }
        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }

    /** @return list<BackupTarget> */
    private function targets(): array
    {
        $only = $this->csv('only');
        $without = $this->csv('without');

        if ($only !== null && $without !== null) {
            throw new BackupFailedException('Use either --only or --without, not both.');
        }

        if ($only !== null) {
            return $only;
        }

        return array_values(array_filter(BackupTarget::defaults(), static fn (BackupTarget $target): bool => ! in_array($target, $without ?? [], true)));
    }

    /** @return list<BackupTarget>|null */
    private function csv(string $option): ?array
    {
        $value = $this->option($option);
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $targets = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $name) {
            $target = BackupTarget::tryFrom($name)
                ?? throw new BackupFailedException("Unknown target '{$name}'; allowed: ".implode(', ', array_column(BackupTarget::cases(), 'value')).'.');
            $targets[$target->value] = $target;
        }

        return array_values($targets);
    }
}
