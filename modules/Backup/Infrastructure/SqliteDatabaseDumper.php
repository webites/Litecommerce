<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Infrastructure;

use Illuminate\Database\Connection;
use Litecommerce\Modules\Backup\Contracts\BackupFailedException;

final class SqliteDatabaseDumper implements DatabaseDumper
{
    public function __construct(private readonly Connection $connection) {}

    public function method(): string
    {
        return 'sqlite-vacuum';
    }

    public function entryName(): string
    {
        return 'database.sqlite';
    }

    public function dumpTo(string $targetPath): void
    {
        @unlink($targetPath);
        $this->connection->getPdo()->exec('VACUUM INTO '.$this->connection->getPdo()->quote($targetPath));

        if (! is_file($targetPath)) {
            throw new BackupFailedException('SQLite did not produce a database copy.');
        }
    }
}
