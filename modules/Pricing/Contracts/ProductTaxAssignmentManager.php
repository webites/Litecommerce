<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface ProductTaxAssignmentManager
{
    /**
     * Passing null clears the assignment — the product falls back to the
     * established default behaviour of a 0% rate.
     */
    public function assign(int $productId, ?int $taxRateId): void;
}
