<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

enum BackupTarget: string
{
    case Database = 'database';
    case PublicFiles = 'public';
    case PrivateFiles = 'private';
    case Env = 'env';

    /** @return list<self> */
    public static function defaults(): array
    {
        return [self::Database, self::PublicFiles, self::PrivateFiles];
    }
}
