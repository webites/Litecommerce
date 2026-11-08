<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface TaxRateCatalog
{
    public function find(int $id): ?TaxRateData;

    /**
     * @return list<TaxRateData>
     */
    public function all(): array;
}
