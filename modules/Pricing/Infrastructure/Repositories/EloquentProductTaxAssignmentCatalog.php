<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ProductTaxAssignment;

final class EloquentProductTaxAssignmentCatalog implements ProductTaxAssignmentCatalog
{
    public function find(int $productId): ?int
    {
        $assignment = ProductTaxAssignment::query()->find($productId);

        return $assignment === null ? null : (int) $assignment->tax_rate_id;
    }
}
