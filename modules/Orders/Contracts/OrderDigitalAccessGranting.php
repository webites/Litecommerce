<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * The staff decision to honour a late payment with digital access. Payment
 * confirmation never grants access for a late payment on its own.
 */
interface OrderDigitalAccessGranting
{
    /**
     * Grants access to the order's digital lines and emails the customer the
     * download links. Idempotent: repeating it creates no further grants and
     * returns 0.
     *
     * @return int number of newly created grants
     *
     * @throws DigitalGrantRefusedException
     */
    public function grantAfterLatePayment(string $orderId, string $actorId, string $reason): int;
}
