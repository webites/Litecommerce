<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface VariantPriceCatalog
{
    public function find(int $variantId): ?VariantPriceData;
}
