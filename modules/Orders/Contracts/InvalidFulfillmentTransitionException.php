<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

use DomainException;

final class InvalidFulfillmentTransitionException extends DomainException
{
    public const NOT_PICKUP = 'not_pickup';

    public const ORDER_NOT_FULFILLABLE = 'order_not_fulfillable';

    public const PAYMENT_REQUIRED = 'payment_required';

    public const WRONG_STATE = 'wrong_state';

    public const DEADLINE_IN_PAST = 'deadline_in_past';

    public const STOCK_UNAVAILABLE = 'stock_unavailable';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
