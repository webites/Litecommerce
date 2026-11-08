<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

final readonly class ProductSearchResult
{
    /**
     * @param  list<SearchProductHitData>  $items
     * @param  list<SearchFilterData>  $filters
     */
    public function __construct(public array $items, public array $filters, public int $total) {}
}
