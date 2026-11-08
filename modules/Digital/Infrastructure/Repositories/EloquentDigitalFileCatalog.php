<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Repositories;

use Litecommerce\Modules\Digital\Contracts\DigitalFileCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalFileData;
use Litecommerce\Modules\Digital\Infrastructure\DigitalDataMapper;
use Litecommerce\Modules\Digital\Infrastructure\Models\VariantFileSet;

final class EloquentDigitalFileCatalog implements DigitalFileCatalog
{
    public function listForVariant(int $variantId): array
    {
        return VariantFileSet::query()->where('variant_id', $variantId)
            ->with('currentVersion')->orderBy('position')->orderBy('id')->get()
            ->map(static fn (VariantFileSet $fileSet): DigitalFileData => DigitalDataMapper::toFileData($fileSet))
            ->values()->all();
    }

    public function find(int $fileId): ?DigitalFileData
    {
        $fileSet = VariantFileSet::query()->with('currentVersion')->find($fileId);

        return $fileSet === null ? null : DigitalDataMapper::toFileData($fileSet);
    }

    public function listPurchasableForVariant(int $variantId): array
    {
        return VariantFileSet::query()->where('variant_id', $variantId)
            ->where('active', true)->whereNotNull('current_version_id')
            ->with('currentVersion')->orderBy('position')->orderBy('id')->get()
            ->map(static fn (VariantFileSet $fileSet): DigitalFileData => DigitalDataMapper::toFileData($fileSet))
            ->values()->all();
    }
}
