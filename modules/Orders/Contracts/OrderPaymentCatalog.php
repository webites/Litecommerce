<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

interface OrderPaymentCatalog
{
    public function find(string $orderId): ?OrderPaymentData;

    /** @return list<string> */
    public function dueOrderIds(int $limit = 100): array;
}
