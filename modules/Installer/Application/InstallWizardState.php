<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

/**
 * Thin session wrapper for installer steps 2-3 (database, admin). No
 * database exists yet at this point (see EnsureLitecommerceIsInstalled —
 * file session until the lock exists), so this data can't be persisted in
 * a table. Cheap to redo; only finalize gets a persistent SetupCheck.
 */
final class InstallWizardState
{
    private const DATABASE_KEY = 'installer.database';

    private const ADMINISTRATOR_KEY = 'installer.administrator';

    /**
     * @param  array{host:string,port:string,database:string,username:string,password:string}  $credentials
     */
    public function rememberDatabaseCredentials(array $credentials): void
    {
        session([self::DATABASE_KEY => $credentials]);
    }

    /**
     * @return array{host:string,port:string,database:string,username:string,password:string}|null
     */
    public function databaseCredentials(): ?array
    {
        return session(self::DATABASE_KEY);
    }

    /**
     * @param  array{name:string,email:string,hashed_password:string}  $administrator
     */
    public function rememberAdministrator(array $administrator): void
    {
        session([self::ADMINISTRATOR_KEY => $administrator]);
    }

    /**
     * @return array{name:string,email:string,hashed_password:string}|null
     */
    public function administrator(): ?array
    {
        return session(self::ADMINISTRATOR_KEY);
    }

    public function clear(): void
    {
        session()->forget([self::DATABASE_KEY, self::ADMINISTRATOR_KEY]);
    }
}
