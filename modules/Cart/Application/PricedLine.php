<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

/**
 * Internal to Cart — the live-priced view of one cart/draft line, always
 * recomputed from current Catalog/Pricing/Warehouse state rather than
 * cached, per "Ceny i dostępność aktualizowane" in
 * docs/knowledge-base/05-orders.md. $unavailableReason is one of
 * 'unavailable' (variant missing/disabled), 'no_price' (PriceQuoter has
 * nothing for this currency) or 'insufficient_stock'.
 */
final readonly class PricedLine
{
    /** @param  list<array{code:string,amount:string,base_amount:string}>  $couponAllocations */
    public function __construct(
        public int $variantId,
        /** Null only for a variant Catalog no longer knows at all (e.g. deleted while sitting in a cart) — nothing to look up an image for. */
        public ?int $productId,
        public string $sku,
        public string $displayName,
        public ?string $productType,
        public string $quantity,
        public ?string $unitAmount,
        public ?string $lineNetAmount,
        public ?string $lineVatAmount,
        public ?string $lineGrossAmount,
        public bool $available,
        public ?string $unavailableReason,
        public bool $promotionalActive = false,
        public string $couponDiscountAmount = '0',
        public array $couponAllocations = [],
    ) {}
}
