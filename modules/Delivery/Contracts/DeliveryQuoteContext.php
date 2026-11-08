<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * Typed checkout context handed to delivery pricing, per the "Pipeline
 * wyceny" step in docs/knowledge-base/05-orders.md ("Przekaż metodom
 * dostawy typowany kontekst z adresem [...] oraz kwotą produktów po
 * rabatach").
 */
final readonly class DeliveryQuoteContext
{
    /**
     * @param  ?string  $countryCode  ISO 3166-1 alpha-2. Null when no
     *                                shipping address is known yet — a flat-rate region match then
     *                                cannot be resolved, but pickup remains quotable.
     * @param  string  $goodsAmountForThreshold  Gross value of physical and
     *                                           digital lines after discounts, excluding delivery itself — the
     *                                           free-shipping-threshold comparison base per 06-fulfillment.md.
     */
    public function __construct(
        public string $currencyCode,
        public ?string $countryCode,
        public string $goodsAmountForThreshold,
    ) {}
}
