<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * Optional capability of a ShippingMethod: the countries it can ever quote.
 * A method that does not implement it is treated as selling everywhere.
 */
interface RestrictsSellableCountries
{
    /** @return list<string> ISO 3166-1 alpha-2 codes */
    public function sellableCountryCodes(): array;
}
