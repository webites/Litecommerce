<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Domain;

/** What the merchant decides about a shipping method, independent of who provides it. */
final readonly class MerchantShippingSettings
{
    public function __construct(
        public bool $active,
        public int $position,
        public ?int $taxRateId,
        public bool $allowCashOnDelivery,
        public bool $allowPayOnPickup,
        public ?int $pickupDeadlineDays = null,
    ) {}

    public static function inactive(): self
    {
        return new self(false, 0, null, false, false);
    }
}
