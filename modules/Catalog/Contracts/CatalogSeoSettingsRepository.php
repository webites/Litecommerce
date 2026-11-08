<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** The only way outside Catalog (the panel's SEO settings page) reads the store-wide default variant SEO mode. Writes go through Catalog's own panel endpoint, never this contract — see Catalog\Application\UpdateCatalogSeoSettings. */
interface CatalogSeoSettingsRepository
{
    public function current(): CatalogSeoSettingsData;
}
