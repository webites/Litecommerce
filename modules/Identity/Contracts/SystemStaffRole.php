<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

enum SystemStaffRole: string
{
    case Administrator = 'administrator';
    case StoreManager = 'store_manager';
    case WarehouseWorker = 'warehouse_worker';
    case ContentEditor = 'content_editor';
}
