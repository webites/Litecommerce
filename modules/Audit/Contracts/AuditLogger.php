<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Contracts;

interface AuditLogger
{
    public function record(AuditEntryData $entry): void;
}
