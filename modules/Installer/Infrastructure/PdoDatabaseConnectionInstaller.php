<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Infrastructure;

use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Installer\Application\DatabaseConnectionResult;
use PDO;
use PDOException;

final class PdoDatabaseConnectionInstaller implements DatabaseConnectionInstaller
{
    public function connect(array $credentials): DatabaseConnectionResult
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $credentials['host'],
            $credentials['port'],
            $credentials['database'],
        );

        try {
            new PDO($dsn, $credentials['username'], $credentials['password'], [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return new DatabaseConnectionResult(successful: true);
        } catch (PDOException $e) {
            logger()->warning('Installer: database connection failed.', [
                'host' => $credentials['host'],
                'port' => $credentials['port'],
                'database' => $credentials['database'],
            ]);

            return new DatabaseConnectionResult(
                successful: false,
                errorMessage: trans('installer::messages.connection_failed'),
            );
        }
    }
}
