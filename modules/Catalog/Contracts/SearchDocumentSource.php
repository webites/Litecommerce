<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

interface SearchDocumentSource
{
    /**
     * Returns every locale document for at most $limit enabled variants with ids greater than $afterVariantId.
     *
     * @return array{items: list<SearchDocumentData>, lastVariantId: int|null}
     */
    public function batchAfter(int $afterVariantId, int $limit): array;
}
