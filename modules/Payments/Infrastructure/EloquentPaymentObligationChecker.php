<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure;

use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentObligationChecker;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class EloquentPaymentObligationChecker implements PaymentObligationChecker
{
    public function hasOpenAttemptsForMethod(string $methodId): bool
    {
        return PaymentAttempt::query()
            ->where('method_id', $methodId)
            ->whereIn('status', [PaymentAttemptStatus::Created->value, PaymentAttemptStatus::Pending->value])
            ->exists();
    }
}
