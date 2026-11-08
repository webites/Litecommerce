<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

/**
 * Reports a line quantity that was capped while merging a guest cart into
 * an account cart, per "sprawdzamy limity, informujemy o korektach" in
 * docs/knowledge-base/05-orders.md.
 */
final readonly class CartMergeAdjustment
{
    public function __construct(
        public int $variantId,
        public string $requestedQuantity,
        public string $adjustedQuantity,
    ) {}
}
