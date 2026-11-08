<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

final readonly class CheckoutAddressData
{
    public function __construct(
        public string $recipientName,
        public ?string $companyName,
        public string $countryCode,
        public string $postalCode,
        public string $city,
        public ?string $region,
        public string $addressLine1,
        public ?string $addressLine2,
        public ?string $phone,
    ) {}
}
