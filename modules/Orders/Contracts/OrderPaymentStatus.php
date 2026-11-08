<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case LatePayment = 'late_payment';
    case Overpaid = 'overpaid';
}
