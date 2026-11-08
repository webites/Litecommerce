<?php

declare(strict_types=1);

namespace Tests\Unit\Backup;

use Illuminate\Database\Connection;
use Litecommerce\Modules\Backup\Infrastructure\MysqldumpDatabaseDumper;
use Litecommerce\Modules\Backup\Infrastructure\PdoMysqlDatabaseDumper;
use Mockery;
use PHPUnit\Framework\TestCase;

final class DatabaseDumperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function dumper(): PdoMysqlDatabaseDumper
    {
        return new PdoMysqlDatabaseDumper(Mockery::mock(Connection::class));
    }

    /** @param list<array{Field: string, Type: string, Extra: string}> $rows */
    private function columns(array $rows): array
    {
        $statement = Mockery::mock(\PDOStatement::class);
        $statement->shouldReceive('fetchAll')->andReturn($rows);
        $pdo = Mockery::mock(\PDO::class);
        $pdo->shouldReceive('query')->andReturn($statement);

        $method = new \ReflectionMethod(PdoMysqlDatabaseDumper::class, 'columns');

        return $method->invoke($this->dumper(), $pdo, '`t`');
    }

    public function test_columns_with_a_default_expression_are_exported_and_only_computed_ones_are_skipped(): void
    {
        $columns = $this->columns([
            ['Field' => 'id', 'Type' => 'bigint unsigned', 'Extra' => 'auto_increment'],
            ['Field' => 'created_at', 'Type' => 'timestamp', 'Extra' => 'DEFAULT_GENERATED'],
            ['Field' => 'updated_at', 'Type' => 'timestamp', 'Extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'],
            ['Field' => 'slug_hash', 'Type' => 'varchar(64)', 'Extra' => 'VIRTUAL GENERATED'],
            ['Field' => 'total', 'Type' => 'decimal(12,2)', 'Extra' => 'STORED GENERATED'],
            ['Field' => 'legacy', 'Type' => 'int', 'Extra' => 'PERSISTENT'],
            ['Field' => 'payload', 'Type' => 'blob', 'Extra' => ''],
            ['Field' => 'ratio', 'Type' => 'double', 'Extra' => ''],
        ]);

        $this->assertSame(['id' => 'numeric', 'created_at' => 'text', 'updated_at' => 'text', 'payload' => 'binary', 'ratio' => 'float'], $columns);
    }

    public function test_literals_are_escaped_and_typed(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $literal = new \ReflectionMethod(PdoMysqlDatabaseDumper::class, 'literal');
        $format = fn (mixed $value, string $kind): string => $literal->invoke($this->dumper(), $pdo, $value, $kind);

        $this->assertSame('NULL', $format(null, 'text'));
        $this->assertSame('42', $format('42', 'numeric'));
        $this->assertSame('12.50', $format('12.50', 'numeric'));
        $this->assertSame("'it''s'", $format("it's", 'text'));
        $this->assertSame('0x00ff', $format("\x00\xff", 'binary'));
        $this->assertSame("''", $format('', 'binary'));
        $this->assertSame('NULL', $format(NAN, 'float'));
        $this->assertSame('0.1', $format(0.1, 'float'));
    }

    public function test_mysqldump_gets_credentials_through_a_private_option_file_not_the_command_line(): void
    {
        $dumper = new MysqldumpDatabaseDumper(['database' => 'shop', 'username' => 'user', 'password' => 'p"a\\ss', 'host' => 'db.local', 'port' => 3307], 'mysqldump');

        $this->assertSame("[client]\nuser=\"user\"\npassword=\"p\\\"a\\\\ss\"\nhost=\"db.local\"\nport=3307\n", $dumper->optionFile());

        $command = $dumper->command('/tmp/opts', '/tmp/out.sql');
        $this->assertSame('--defaults-extra-file=/tmp/opts', $command[1]);
        $this->assertContains('--single-transaction', $command);
        $this->assertSame('shop', end($command));
        $this->assertStringNotContainsString('p"a', implode(' ', $command));
    }

    public function test_a_unix_socket_replaces_host_and_port(): void
    {
        $dumper = new MysqldumpDatabaseDumper(['database' => 'shop', 'username' => 'u', 'password' => '', 'unix_socket' => '/var/run/mysqld.sock'], 'mysqldump');

        $this->assertStringContainsString('socket="/var/run/mysqld.sock"', $dumper->optionFile());
        $this->assertStringNotContainsString('host=', $dumper->optionFile());
    }
}
