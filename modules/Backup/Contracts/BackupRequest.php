<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

final readonly class BackupRequest
{
    /**
     * @param  list<BackupTarget>  $targets
     * @param  list<string>  $extraPaths  files or directories inside the application root
     * @param  list<string>  $excludes  glob patterns relative to the application root
     * @param  string  $databaseMethod  auto, mysqldump or php
     */
    public function __construct(
        public array $targets,
        public array $extraPaths = [],
        public array $excludes = [],
        public ?string $outputDirectory = null,
        public ?string $label = null,
        public string $databaseMethod = 'auto',
        public ?int $keep = null,
    ) {}
}
