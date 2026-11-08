<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

enum PaymentAttemptStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Overpayment = 'overpayment';
    case LatePayment = 'late_payment';
}
