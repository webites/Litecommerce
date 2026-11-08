<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Digital\Infrastructure\DigitalDataMapper;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;

final class EloquentDigitalAccessCatalog implements DigitalAccessCatalog
{
    public function findForOrder(string $orderId): array
    {
        return $this->map(DownloadGrant::query()->where('order_id', $orderId));
    }

    public function findByReference(string $referenceType, string $referenceId): array
    {
        return $this->map(DownloadGrant::query()->where('reference_type', $referenceType)->where('reference_id', $referenceId));
    }

    public function find(int $grantId): ?DigitalGrantData
    {
        $grant = DownloadGrant::query()->with(['fileSet', 'version'])->find($grantId);

        return $grant === null ? null : DigitalDataMapper::toGrantData($grant);
    }

    /**
     * @param  Builder<DownloadGrant>  $query
     * @return list<DigitalGrantData>
     */
    private function map(Builder $query): array
    {
        return $query->with(['fileSet', 'version'])->orderBy('id')->get()
            ->map(static fn (DownloadGrant $grant): DigitalGrantData => DigitalDataMapper::toGrantData($grant))
            ->values()->all();
    }
}
