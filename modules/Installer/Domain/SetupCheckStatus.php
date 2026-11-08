<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Domain;

enum SetupCheckStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
