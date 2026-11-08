<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * The price a shipping method asks for, before tax. Core applies the
 * merchant-assigned tax rate and the shop's price entry mode, so every
 * method is split into net/VAT/gross by one code path.
 */
final readonly class ShippingMethodQuote
{
    /**
     * @param  string  $amount  Already rounded to the quote currency's decimal places,
     *                          interpreted under PricingSettingsCatalog::entryMode().
     * @param  list<DeliveryPickupPointData>  $pickupPoints  Only for a pickup kind.
     */
    public function __construct(
        public string $amount,
        public bool $freeThresholdApplied = false,
        public array $pickupPoints = [],
    ) {}
}
