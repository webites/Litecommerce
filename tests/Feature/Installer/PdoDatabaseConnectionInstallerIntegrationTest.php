<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Litecommerce\Modules\Installer\Infrastructure\PdoDatabaseConnectionInstaller;
use Tests\TestCase;

/**
 * The only test in the suite touching a real network connection — verifies
 * the actual PDO implementation against the docker-compose "mysql" service.
 * Skips gracefully when that host isn't reachable (e.g. outside Docker, or
 * in CI without a MySQL service).
 */
final class PdoDatabaseConnectionInstallerIntegrationTest extends TestCase
{
    private const HOST = 'mysql';

    private const PORT = 3306;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = @fsockopen(self::HOST, self::PORT, timeout: 1);

        if ($connection === false) {
            $this->markTestSkipped('MySQL host "'.self::HOST.'" is not reachable.');
        }

        fclose($connection);
    }

    public function test_it_connects_to_the_real_mysql_container_with_valid_credentials(): void
    {
        $result = (new PdoDatabaseConnectionInstaller)->connect([
            'host' => self::HOST,
            'port' => (string) self::PORT,
            'database' => 'litecommerce',
            'username' => 'litecommerce',
            'password' => 'litecommerce',
        ]);

        $this->assertTrue($result->successful);
    }

    public function test_it_reports_failure_for_invalid_credentials(): void
    {
        $result = (new PdoDatabaseConnectionInstaller)->connect([
            'host' => self::HOST,
            'port' => (string) self::PORT,
            'database' => 'litecommerce',
            'username' => 'litecommerce',
            'password' => 'definitely-wrong-password',
        ]);

        $this->assertFalse($result->successful);
        $this->assertNotNull($result->errorMessage);
    }
}
