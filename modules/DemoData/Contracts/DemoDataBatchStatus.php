<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

enum DemoDataBatchStatus: string
{
    case Creating = 'creating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Removing = 'removing';
    case RemovalFailed = 'removal_failed';
    case Removed = 'removed';
}
