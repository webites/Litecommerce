<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentObligationChecker
{
    public function hasOpenAttemptsForMethod(string $methodId): bool;
}
