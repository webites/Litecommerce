<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class CatalogSeoSettingsData
{
    public function __construct(
        public string $defaultVariantSeoMode,
    ) {}
}
