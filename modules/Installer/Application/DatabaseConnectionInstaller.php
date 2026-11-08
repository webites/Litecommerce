<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

interface DatabaseConnectionInstaller
{
    /**
     * @param  array{host:string,port:string,database:string,username:string,password:string}  $credentials
     */
    public function connect(array $credentials): DatabaseConnectionResult;
}
