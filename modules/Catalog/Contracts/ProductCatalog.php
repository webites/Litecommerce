<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** Public, product-level (not variant-level — see VariantCatalog) read for the storefront. Both methods only ever return published products. */
interface ProductCatalog
{
    public function findBySlug(string $slug, string $locale): ?ProductViewData;

    /** The SAME published product's own slug in another locale (e.g. for a storefront language switcher that stays on the equivalent page) — null when unpublished or no translation exists in that locale. */
    public function findSlugInLocale(int $id, string $locale): ?string;

    /** @return array{items: list<ProductSummaryData>, total: int} */
    public function paginatePublished(string $locale, ?int $categoryId, int $perPage, int $page): array;

    /** @return array{items: list<ProductSummaryData>, total: int} */
    public function paginateFeatured(string $locale, int $perPage, int $page): array;

    /** @return array{items: list<ProductSummaryData>, total: int} */
    public function paginateByBrand(string $locale, int $brandId, int $perPage, int $page): array;

    /**
     * Published-only, in the same order as $ids — a missing/unpublished id is silently dropped.
     *
     * @param  list<int>  $ids
     * @return list<ProductSummaryData>
     */
    public function findManyPublished(array $ids, string $locale): array;

    /** Published-only free-text search (name or variant SKU) — e.g. the Cms product-grid block's product picker. @return list<ProductSummaryData> */
    public function search(string $term, string $locale, int $limit): array;
}
