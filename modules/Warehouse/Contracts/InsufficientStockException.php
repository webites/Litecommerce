<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

/**
 * Thrown by StockReservationManager::reserve() when the requested quantity
 * exceeds available stock and the variant does not allow selling past
 * stock — the future Cart/Checkout caller is expected to catch this and
 * present the item as unavailable, per docs/knowledge-base/06-fulfillment.md.
 */
final class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly int $variantId,
        public readonly string $requestedQuantity,
        public readonly string $availableQuantity,
    ) {
        parent::__construct(
            "Insufficient stock for variant {$variantId}: requested {$requestedQuantity}, available {$availableQuantity}.",
        );
    }
}
