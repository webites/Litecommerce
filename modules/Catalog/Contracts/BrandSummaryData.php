<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class BrandSummaryData
{
    public function __construct(
        public int $id,
        public string $name,
        /** Resolves to the brand's own archive page — route('storefront.cms.show', ['locale' => ..., 'slug' => $slug]), never a query-filtered listing. */
        public string $slug,
    ) {}
}
