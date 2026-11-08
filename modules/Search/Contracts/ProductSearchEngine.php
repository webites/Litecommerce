<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

interface ProductSearchEngine
{
    public function search(ProductSearchCriteria $criteria): ProductSearchResult;
}
