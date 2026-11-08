<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

/**
 * Read side consumed by Orders\Presentation\Controllers\OrderController
 * to show an order's digital access section, the same pattern as
 * Payments\Contracts\PaymentAttemptCatalog::forOrder().
 */
interface DigitalAccessCatalog
{
    /** @return list<DigitalGrantData> */
    public function findForOrder(string $orderId): array;

    /** @return list<DigitalGrantData> */
    public function findByReference(string $referenceType, string $referenceId): array;

    public function find(int $grantId): ?DigitalGrantData;
}
