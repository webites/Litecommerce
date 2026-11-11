<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/** Phone numbers in international notation; a value that is not a valid number is returned as typed. */
interface PhoneFormatter
{
    /** @param string|null $countryCode ISO 3166-1 alpha-2 country used to read a number written without a "+" prefix */
    public function format(string $phone, ?string $countryCode = null): string;
}
