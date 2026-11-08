<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Litecommerce\Modules\Catalog\Contracts\CatalogSeoSettingsData;
use Litecommerce\Modules\Catalog\Contracts\CatalogSeoSettingsRepository;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CatalogSeoSettings;

final class EloquentCatalogSeoSettingsRepository implements CatalogSeoSettingsRepository
{
    public function current(): CatalogSeoSettingsData
    {
        return new CatalogSeoSettingsData(
            defaultVariantSeoMode: CatalogSeoSettings::current()->default_variant_seo_mode,
        );
    }
}
