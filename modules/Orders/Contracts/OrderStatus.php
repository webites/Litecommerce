<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Processing = 'processing';
    case Cancelled = 'cancelled';
    case PaymentReview = 'payment_review';
}
