<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

interface UpdateChecker
{
    public function status(): UpdateStatus;

    /** Contacts the release source now; a failure is recorded in the status, not thrown. */
    public function checkNow(): UpdateStatus;

    public function setAutomaticChecks(bool $enabled): void;
}
