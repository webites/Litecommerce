<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentAttemptCatalog
{
    /** @return list<PaymentAttemptData> */
    public function forOrder(string $orderId): array;
}
