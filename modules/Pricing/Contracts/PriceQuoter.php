<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface PriceQuoter
{
    /**
     * Computes the effective unit price and line VAT split for a variant,
     * in the given currency, for the given quantity (a decimal string, to
     * support fractional physical quantities).
     *
     * Returns null when the variant has no base price configured yet, or
     * when the requested currency is neither the base currency nor has a
     * manual exchange rate configured for it.
     */
    public function quote(int $variantId, string $currencyCode, string $quantity): ?PriceQuoteData;

    /**
     * Bulk equivalent used by listings and search projections.
     *
     * @param  list<int>  $variantIds
     * @return array<int, PriceQuoteData> keyed by variant id; unpriced variants are omitted
     */
    public function quoteMany(array $variantIds, string $currencyCode, string $quantity): array;

    /**
     * Bulk quote with a quantity per variant, for carts and checkout drafts.
     *
     * @param  array<int, string>  $quantitiesByVariantId
     * @return array<int, PriceQuoteData> keyed by variant id; unpriced variants are omitted
     */
    public function quoteLines(array $quantitiesByVariantId, string $currencyCode): array;
}
