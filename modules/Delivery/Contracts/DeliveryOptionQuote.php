<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * Always represents a currently purchasable option — an unavailable method
 * (excluded region, no matching region, inactive) is never returned by
 * DeliveryMethodCatalog, per "Metody niedostępne ukryte, bez powodów dla
 * klienta" in docs/knowledge-base/06-fulfillment.md.
 */
final readonly class DeliveryOptionQuote
{
    /** @param list<DeliveryPickupPointData> $pickupPoints */
    public function __construct(
        public string $methodId,
        public string $providerId,
        public string $kind,
        public string $name,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
        public bool $freeThresholdApplied,
        public bool $supportsCashOnDelivery,
        public bool $supportsPayOnPickup,
        public array $pickupPoints,
    ) {}
}
