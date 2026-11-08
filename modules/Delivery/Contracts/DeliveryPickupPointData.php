<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

final readonly class DeliveryPickupPointData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $countryCode,
        public string $postalCode,
        public string $city,
        public ?string $region,
        public string $addressLine1,
        public ?string $addressLine2,
        public ?string $instructions,
    ) {}
}
