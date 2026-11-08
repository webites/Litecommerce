<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

interface VariantCatalog
{
    public function find(int $variantId): ?VariantData;

    /**
     * Bulk, paginated listing of physical variants for cross-product
     * views (e.g. a warehouse-wide stock list) — never resolve a listing
     * with one find() call per row.
     *
     * @return array{items: list<VariantSummaryData>, total: int}
     */
    public function paginatePhysical(string $locale, int $perPage, int $page): array;

    /**
     * Bulk read by id, of either product type, for callers that already
     * hold a list of variant ids and need display name, quantity bounds
     * and type in one query (e.g. pricing/validating cart lines) — never
     * resolve this with one find() call per row.
     *
     * @param  list<int>  $variantIds
     * @return array<int, VariantSummaryData> keyed by variant id; unknown
     *                                        ids are omitted
     */
    public function findMany(array $variantIds, string $locale): array;
}
