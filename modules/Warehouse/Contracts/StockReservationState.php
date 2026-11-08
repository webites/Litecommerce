<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

enum StockReservationState: string
{
    /** Holds quantity temporarily, until expires_at unless renewed/confirmed. */
    case Pending = 'pending';

    /** Payment confirmed (or no online gate applies) — holds indefinitely until fulfilled or released. */
    case Confirmed = 'confirmed';

    /** Settled: physical stock was decreased. Terminal. */
    case Fulfilled = 'fulfilled';

    /** Cancelled before fulfillment — never touched physical stock. Terminal. */
    case Released = 'released';

    /** Reconciled away after expires_at with no confirmation. Terminal. */
    case Expired = 'expired';
}
