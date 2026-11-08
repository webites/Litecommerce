<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

final readonly class BackupResult
{
    /**
     * @param  list<BackupTarget>  $targets
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $path,
        public int $sizeBytes,
        public string $sha256,
        public \DateTimeImmutable $createdAt,
        public array $targets,
        public int $fileCount,
        public ?string $databaseMethod,
        public array $warnings,
        public int $removedOldBackups,
    ) {}
}
