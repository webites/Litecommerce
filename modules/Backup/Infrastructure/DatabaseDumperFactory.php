<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Infrastructure;

use Illuminate\Database\Connection;
use Litecommerce\Modules\Backup\Contracts\BackupFailedException;

final class DatabaseDumperFactory
{
    public function __construct(private readonly Connection $connection) {}

    /** @param string $method auto, mysqldump or php */
    public function make(string $method): DatabaseDumper
    {
        $driver = $this->connection->getDriverName();

        if ($driver === 'sqlite') {
            return new SqliteDatabaseDumper($this->connection);
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new BackupFailedException("Backups of the '{$driver}' database driver are not supported.");
        }

        if (! in_array($method, ['auto', 'mysqldump', 'php'], true)) {
            throw new BackupFailedException("Unknown database method '{$method}'; use auto, mysqldump or php.");
        }

        if ($method !== 'php') {
            $dumper = MysqldumpDatabaseDumper::locate($this->connection->getConfig(), config('backup.mysqldump_path'));
            if ($dumper !== null) {
                return $dumper;
            }
            if ($method === 'mysqldump') {
                throw new BackupFailedException('mysqldump is not available on this host; use --db-method=php.');
            }
        }

        return new PdoMysqlDatabaseDumper($this->connection, (int) config('backup.insert_batch_bytes', 1048576));
    }
}
