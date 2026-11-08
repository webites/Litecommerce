<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Installer\Application\DatabaseConnectionResult;

final class AlwaysSuccessfulDatabaseConnectionInstaller implements DatabaseConnectionInstaller
{
    public function connect(array $credentials): DatabaseConnectionResult
    {
        return new DatabaseConnectionResult(successful: true);
    }
}
