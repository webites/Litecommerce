<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/** First-time delivery configuration used by the setup wizard. */
interface DeliverySetup
{
    /** Adds an active local pickup method with its first pickup point. */
    public function addLocalPickup(string $methodName, string $pointName, string $countryCode, string $postalCode, string $city, string $addressLine): void;

    /** Adds an active courier method with one rate for the given country, in the base currency. */
    public function addCourier(string $methodName, string $countryCode, string $price): void;
}
