<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentManager;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ProductTaxAssignment;

final class EloquentProductTaxAssignmentManager implements ProductTaxAssignmentManager
{
    public function assign(int $productId, ?int $taxRateId): void
    {
        if ($taxRateId === null) {
            ProductTaxAssignment::query()->where('product_id', $productId)->delete();

            return;
        }

        ProductTaxAssignment::query()->updateOrCreate(
            ['product_id' => $productId],
            ['tax_rate_id' => $taxRateId],
        );
    }
}
