<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

interface CronConfigurator
{
    /** Cheap check, safe on every page load: spawns no process. */
    public function canAttempt(): bool;

    /** Registers one marked per-minute entry in the hosting user's crontab; idempotent. */
    public function install(): CronSetupResult;
}
