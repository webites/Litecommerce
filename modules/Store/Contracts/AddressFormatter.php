<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/** Postal address snapshots (recipient_name, company_name, address_line_1/2, postal_code, city, region, country_code, phone) as display lines. */
interface AddressFormatter
{
    /**
     * @param  array<string, mixed>  $address
     * @return list<string>
     */
    public function lines(array $address, ?string $locale = null, bool $includePhone = false): array;

    /** @param array<string, mixed> $address */
    public function inline(array $address, ?string $locale = null, bool $includePhone = false): string;

    public function countryName(string $countryCode, ?string $locale = null): string;
}
