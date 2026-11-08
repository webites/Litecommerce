<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface VariantPriceManager
{
    /**
     * Sets the variant's price in the shop's base currency. Passing null
     * for the promotional fields clears the promotional price.
     *
     * No comparison between regular and promotional amounts is enforced
     * here — an invalid combination (promotional not lower than regular)
     * is accepted and simply not applied when a price is quoted, per the
     * resolved handling in docs/knowledge-base/04-pricing-coupons.md.
     */
    public function setBasePrice(
        int $variantId,
        string $regularAmount,
        PriceMode $regularMode,
        ?string $promotionalAmount,
        ?PriceMode $promotionalMode,
    ): void;

    /**
     * Sets a currency-specific override. Each of the four values is
     * independent: null means "no override for this field", falling back
     * to a converted price from the base currency.
     */
    public function setCurrencyOverride(
        int $variantId,
        string $currencyCode,
        ?string $regularAmount,
        ?PriceMode $regularMode,
        ?string $promotionalAmount,
        ?PriceMode $promotionalMode,
    ): void;
}
