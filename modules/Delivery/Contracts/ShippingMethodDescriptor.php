<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * Identity and capabilities of a shipping method. The id is the stable
 * method key used by checkout drafts, order snapshots and Payments gating:
 * "<providerId>.<name>", with the provider prefix "core" reserved for the
 * configurations managed in the Delivery settings tab.
 */
final readonly class ShippingMethodDescriptor
{
    public function __construct(
        public string $id,
        public string $providerId,
        public string $name,
        public bool $supportsCashOnDelivery = false,
        public bool $supportsPayOnPickup = false,
        public DeliveryKind $kind = DeliveryKind::FlatRate,
    ) {}
}
