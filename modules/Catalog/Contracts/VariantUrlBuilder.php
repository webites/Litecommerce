<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/**
 * Builds the canonical "{axis-slug}/{value-slug}/..." URL segments for one
 * variant's exact option combination, axes always in product_options.position
 * order — the one true deep-link URL for that combination. Lives in Contracts
 * (not Application) despite being logic, not data: it's a pure function over
 * already-public DTOs (ProductOptionAxisData/ProductOptionValueData) with no
 * I/O, meant to be shared ACROSS modules (Storefront's CatalogController and
 * the sitemap builder) so the two can never disagree on a variant's canonical
 * URL — module boundaries are crossed through Contracts, never Application.
 */
final class VariantUrlBuilder
{
    /**
     * @param  list<ProductOptionAxisData>  $optionAxes
     * @param  array<string, string>  $optionValues  axis key => value key
     * @return ?list<string> null when the combination is incomplete/stale (e.g. a
     *                       variant created before a later axis was added — see
     *                       EloquentProductCatalog::optionAxesAndAssignments()'s docblock)
     */
    public function canonicalSegments(array $optionAxes, array $optionValues): ?array
    {
        $segments = [];
        foreach ($optionAxes as $axis) {
            $value = $this->findValue($axis, $optionValues[$axis->key] ?? null);

            if ($value === null) {
                return null;
            }

            $segments[] = $axis->slug;
            $segments[] = $value->slug;
        }

        return $segments;
    }

    private function findValue(ProductOptionAxisData $axis, ?string $valueKey): ?ProductOptionValueData
    {
        if ($valueKey === null) {
            return null;
        }

        foreach ($axis->values as $value) {
            if ($value->key === $valueKey) {
                return $value;
            }
        }

        return null;
    }
}
