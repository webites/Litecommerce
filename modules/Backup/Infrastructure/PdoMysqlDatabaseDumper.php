<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Infrastructure;

use Illuminate\Database\Connection;
use Litecommerce\Modules\Backup\Contracts\BackupFailedException;

/**
 * Logical MySQL/MariaDB export over PDO for hosts without mysqldump. Reads every table inside one
 * consistent-snapshot transaction (InnoDB) and streams rows to the file, so memory stays flat.
 * Views are not exported; the schema has none.
 */
final class PdoMysqlDatabaseDumper implements DatabaseDumper
{
    public function __construct(private readonly Connection $connection, private readonly int $batchBytes = 1048576) {}

    public function method(): string
    {
        return 'php-pdo';
    }

    public function entryName(): string
    {
        return 'database.sql';
    }

    public function dumpTo(string $targetPath): void
    {
        $pdo = $this->connection->getPdo();
        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new BackupFailedException('Cannot write the database dump.');
        }

        $buffered = $pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $timeZone = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();

        try {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            $this->write($handle, "-- Litecommerce logical dump\nSET NAMES utf8mb4;\nSET TIME_ZONE = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->tables($pdo) as $table) {
                $this->dumpTable($pdo, $handle, $table);
            }

            $this->write($handle, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        } finally {
            $pdo->exec('ROLLBACK');
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            $pdo->exec('SET time_zone = '.$pdo->quote($timeZone));
            fclose($handle);
        }
    }

    /** @return list<string> */
    private function tables(\PDO $pdo): array
    {
        $tables = [];
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM) as $row) {
            $tables[] = (string) $row[0];
        }

        return $tables;
    }

    /** @param resource $handle */
    private function dumpTable(\PDO $pdo, $handle, string $table): void
    {
        $quoted = $this->identifier($table);
        $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(\PDO::FETCH_NUM);
        $this->write($handle, "DROP TABLE IF EXISTS {$quoted};\n".$create[1].";\n\n");

        $columns = $this->columns($pdo, $quoted);
        if ($columns === []) {
            return;
        }

        $names = implode(', ', array_map($this->identifier(...), array_keys($columns)));
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $pdo->query('SELECT '.$names.' FROM '.$quoted);
        $prefix = "INSERT INTO {$quoted} ({$names}) VALUES\n";
        $batch = [];
        $size = 0;

        try {
            while (($row = $statement->fetch(\PDO::FETCH_NUM)) !== false) {
                $values = [];
                foreach (array_values($columns) as $index => $kind) {
                    $values[] = $this->literal($pdo, $row[$index], $kind);
                }
                $tuple = '('.implode(', ', $values).')';
                $batch[] = $tuple;
                $size += strlen($tuple);

                if ($size >= $this->batchBytes) {
                    $this->write($handle, $prefix.implode(",\n", $batch).";\n");
                    $batch = [];
                    $size = 0;
                }
            }
        } finally {
            $statement->closeCursor();
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        if ($batch !== []) {
            $this->write($handle, $prefix.implode(",\n", $batch).";\n");
        }
        $this->write($handle, "\n");
    }

    /** @return array<string, string> column name => numeric|float|binary|text, generated columns left out */
    private function columns(\PDO $pdo, string $quotedTable): array
    {
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM '.$quotedTable)->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            if (preg_match('/\b(VIRTUAL|STORED|PERSISTENT)\b/i', (string) $column['Extra']) === 1) {
                continue;
            }
            $type = strtolower((string) $column['Type']);
            $columns[(string) $column['Field']] = match (true) {
                (bool) preg_match('/^(float|double|real)/', $type) => 'float',
                (bool) preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|year)/', $type) => 'numeric',
                (bool) preg_match('/(blob|binary|bit)/', $type) => 'binary',
                default => 'text',
            };
        }

        return $columns;
    }

    private function literal(\PDO $pdo, mixed $value, string $kind): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($kind === 'float' && is_float($value)) {
            return is_finite($value) ? var_export($value, true) : 'NULL';
        }

        if (($kind === 'numeric' || $kind === 'float') && is_numeric($value)) {
            return (string) $value;
        }

        if ($kind === 'binary') {
            $bytes = (string) $value;

            return $bytes === '' ? "''" : '0x'.bin2hex($bytes);
        }

        return $pdo->quote((string) $value);
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /** @param resource $handle */
    private function write($handle, string $chunk): void
    {
        if (fwrite($handle, $chunk) === false) {
            throw new BackupFailedException('Writing the database dump failed (disk full?).');
        }
    }
}
