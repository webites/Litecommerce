<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;

final readonly class SearchProductHitData
{
    public function __construct(
        public ProductSummaryData $product,
        public SearchVariantHitData $variant,
    ) {}
}
