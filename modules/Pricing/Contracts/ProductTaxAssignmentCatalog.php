<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface ProductTaxAssignmentCatalog
{
    /**
     * Null means no assignment exists — established behaviour is a 0% rate.
     */
    public function find(int $productId): ?int;
}
