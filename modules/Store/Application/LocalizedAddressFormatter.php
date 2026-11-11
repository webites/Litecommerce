<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use App\Support\I18n\CountryDirectory;
use Litecommerce\Modules\Store\Contracts\AddressFormatter;
use Litecommerce\Modules\Store\Contracts\PhoneFormatter;

final class LocalizedAddressFormatter implements AddressFormatter
{
    private const REGION_BEFORE_POSTAL_CODE = ['US', 'CA', 'AU'];

    public function __construct(private readonly CountryDirectory $countries, private readonly PhoneFormatter $phones) {}

    public function lines(array $address, ?string $locale = null, bool $includePhone = false): array
    {
        $country = $this->text($address, 'country_code');
        $lines = [
            $this->text($address, 'recipient_name'),
            $this->text($address, 'company_name'),
            $this->text($address, 'address_line_1'),
            $this->text($address, 'address_line_2'),
            $this->localityLine($address, strtoupper($country)),
            $country === '' ? '' : $this->countryName($country, $locale),
            $includePhone ? $this->phone($address, $country) : '',
        ];

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    public function inline(array $address, ?string $locale = null, bool $includePhone = false): string
    {
        return implode(', ', $this->lines($address, $locale, $includePhone));
    }

    public function countryName(string $countryCode, ?string $locale = null): string
    {
        return $this->countries->nameOf($countryCode, $locale ?? app()->getLocale());
    }

    /** @param array<string, mixed> $address */
    private function localityLine(array $address, string $country): string
    {
        $postalCode = $this->text($address, 'postal_code');
        $city = $this->text($address, 'city');
        $region = $this->text($address, 'region');

        if (in_array($country, self::REGION_BEFORE_POSTAL_CODE, true)) {
            return trim($city.($region === '' ? '' : ', '.$region).' '.$postalCode);
        }

        return trim($postalCode.' '.$city.($region === '' ? '' : ', '.$region));
    }

    /** @param array<string, mixed> $address */
    private function phone(array $address, string $country): string
    {
        $phone = $this->text($address, 'phone');

        return $phone === '' ? '' : $this->phones->format($phone, $country);
    }

    /** @param array<string, mixed> $address */
    private function text(array $address, string $key): string
    {
        $value = $address[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
