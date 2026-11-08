<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Contracts;

enum MaintenanceReason: string
{
    case Manual = 'manual';
    case Update = 'update';
}
