<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class CategorySummaryData
{
    public function __construct(
        public int $id,
        public string $name,
        /** Resolves to the category's own archive page — route('storefront.cms.show', ['locale' => ..., 'slug' => $slug]), never the query-filtered /products listing. */
        public string $slug,
    ) {}
}
