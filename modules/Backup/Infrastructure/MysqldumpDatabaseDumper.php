<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Infrastructure;

use Litecommerce\Modules\Backup\Contracts\BackupFailedException;

/** Delegates to the server's mysqldump binary; credentials go through a private option file, never the command line. */
final class MysqldumpDatabaseDumper implements DatabaseDumper
{
    /** @param array<string, mixed> $config Laravel connection config */
    public function __construct(private readonly array $config, private readonly string $binary) {}

    /** @param array<string, mixed> $config */
    public static function locate(array $config, ?string $configured): ?self
    {
        if (! function_exists('proc_open')) {
            return null;
        }

        foreach ($configured !== null && $configured !== '' ? [$configured] : ['mysqldump', 'mariadb-dump'] as $binary) {
            if (self::runs([$binary, '--version'])) {
                return new self($config, $binary);
            }
        }

        return null;
    }

    public function method(): string
    {
        return 'mysqldump';
    }

    public function entryName(): string
    {
        return 'database.sql';
    }

    public function dumpTo(string $targetPath): void
    {
        $options = tempnam(sys_get_temp_dir(), 'lcdump');
        if ($options === false) {
            throw new BackupFailedException('Cannot create a temporary credentials file for mysqldump.');
        }

        try {
            chmod($options, 0600);
            file_put_contents($options, $this->optionFile());

            if (! self::runs($this->command($options, $targetPath), true)) {
                @unlink($targetPath);
                throw new BackupFailedException('mysqldump failed; run the backup with --db-method=php to use the built-in exporter.');
            }
        } finally {
            @unlink($options);
        }
    }

    /** @return list<string> */
    public function command(string $optionFile, string $targetPath): array
    {
        return [
            $this->binary, '--defaults-extra-file='.$optionFile, '--single-transaction', '--quick', '--routines', '--triggers',
            '--no-tablespaces', '--default-character-set=utf8mb4', '--result-file='.$targetPath, (string) $this->config['database'],
        ];
    }

    public function optionFile(): string
    {
        $quote = static fn (string $value): string => '"'.addcslashes($value, "\\\"\n\r").'"';
        $lines = ['[client]', 'user='.$quote((string) ($this->config['username'] ?? '')), 'password='.$quote((string) ($this->config['password'] ?? ''))];

        if (! empty($this->config['unix_socket'])) {
            $lines[] = 'socket='.$quote((string) $this->config['unix_socket']);
        } else {
            $lines[] = 'host='.$quote((string) ($this->config['host'] ?? '127.0.0.1'));
            $lines[] = 'port='.(int) ($this->config['port'] ?? 3306);
        }

        return implode("\n", $lines)."\n";
    }

    /** @param list<string> $command */
    private static function runs(array $command, bool $failOnError = false): bool
    {
        $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return false;
        }

        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($failOnError && $code !== 0) {
            report(new \RuntimeException('mysqldump exited with '.$code.': '.substr((string) $error, 0, 500)));
        }

        return $code === 0;
    }
}
