<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Installer\Infrastructure\EnvFileWriter;
use Litecommerce\Modules\Media\Contracts\PublicStorageLinker;
use Tests\Doubles\AlwaysSuccessfulDatabaseConnectionInstaller;
use Tests\Doubles\NullPublicStorageLinker;

/**
 * Binds a fake DB connector and an EnvFileWriter pointed at a temp file,
 * so installer happy-path tests never touch the network or the repo's
 * real .env. Also fakes the public storage linker, since it operates on
 * public_path() which UsesIsolatedStorage does not redirect.
 */
trait UsesFakeInstallerDependencies
{
    protected function fakeInstallerDependencies(): void
    {
        $this->app->bind(DatabaseConnectionInstaller::class, AlwaysSuccessfulDatabaseConnectionInstaller::class);
        $this->app->bind(PublicStorageLinker::class, NullPublicStorageLinker::class);

        $envPath = sys_get_temp_dir().'/litecommerce-test-env-'.uniqid();
        file_put_contents($envPath, "APP_KEY=\nDB_CONNECTION=sqlite\n");

        $this->app->bind(EnvFileWriter::class, fn () => new EnvFileWriter($envPath));
    }
}
