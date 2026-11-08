<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\TaxRateData;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;

final class EloquentTaxRateCatalog implements TaxRateCatalog
{
    public function find(int $id): ?TaxRateData
    {
        $rate = TaxRate::query()->find($id);

        return $rate === null ? null : $this->toData($rate);
    }

    public function all(): array
    {
        return TaxRate::query()->orderBy('name')->get()->map($this->toData(...))->values()->all();
    }

    private function toData(TaxRate $rate): TaxRateData
    {
        return new TaxRateData(
            id: (int) $rate->getKey(),
            name: $rate->name,
            kind: TaxQualificationKind::from($rate->kind),
            percentage: $rate->percentage,
        );
    }
}
