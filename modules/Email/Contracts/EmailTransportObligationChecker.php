<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailTransportObligationChecker
{
    public function hasOutstandingForOwner(string $owner): bool;
}
