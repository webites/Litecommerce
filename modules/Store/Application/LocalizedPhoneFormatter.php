<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Litecommerce\Modules\Store\Contracts\PhoneFormatter;

final class LocalizedPhoneFormatter implements PhoneFormatter
{
    public function format(string $phone, ?string $countryCode = null): string
    {
        $phone = trim($phone);
        $region = $countryCode === null || $countryCode === '' ? null : strtoupper($countryCode);
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($phone, $region);
        } catch (NumberParseException) {
            return $phone;
        }

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::INTERNATIONAL) : $phone;
    }
}
