<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

interface SchedulerStatus
{
    public function current(): SchedulerStatusData;
}
