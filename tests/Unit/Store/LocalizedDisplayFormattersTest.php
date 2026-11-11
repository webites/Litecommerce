<?php

declare(strict_types=1);

namespace Tests\Unit\Store;

use App\Support\I18n\CountryDirectory;
use DateTimeImmutable;
use Litecommerce\Modules\Store\Application\LocalizedAddressFormatter;
use Litecommerce\Modules\Store\Application\LocalizedDateTimeFormatter;
use Litecommerce\Modules\Store\Application\LocalizedPercentageFormatter;
use Litecommerce\Modules\Store\Application\LocalizedPhoneFormatter;
use Litecommerce\Modules\Store\Application\LocalizedQuantityFormatter;
use Litecommerce\Modules\Store\Contracts\StoreSettingsData;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use PHPUnit\Framework\TestCase;

final class LocalizedDisplayFormattersTest extends TestCase
{
    private function plain(string $text): string
    {
        return (string) preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $text);
    }

    private function dates(string $timezone): LocalizedDateTimeFormatter
    {
        return new LocalizedDateTimeFormatter(new class($timezone) implements StoreSettingsReader
        {
            public function __construct(private readonly string $timezone) {}

            public function current(): StoreSettingsData
            {
                return new StoreSettingsData('Shop', null, null, null, null, null, true, 24, $this->timezone);
            }
        });
    }

    public function test_date_time_is_converted_to_the_store_zone_and_follows_the_locale(): void
    {
        $moment = new DateTimeImmutable('2026-11-10 12:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('10 lis 2026, 13:30', $this->plain($this->dates('Europe/Warsaw')->dateTime($moment, 'pl')));
        $this->assertSame('Nov 10, 2026, 12:30 PM', $this->plain($this->dates('UTC')->dateTime($moment, 'en')));
        $this->assertSame('Nov 10, 2026, 7:30 AM', $this->plain($this->dates('America/New_York')->dateTime($moment, 'en')));
    }

    public function test_date_uses_the_store_zone_day_boundary(): void
    {
        $lateEvening = new DateTimeImmutable('2026-11-10 23:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('11 lis 2026', $this->dates('Europe/Warsaw')->date($lateEvening, 'pl'));
        $this->assertSame('10 lis 2026', $this->dates('UTC')->date($lateEvening, 'pl'));
    }

    public function test_quantity_drops_trailing_zeros_and_uses_locale_separators(): void
    {
        $formatter = new LocalizedQuantityFormatter;

        $this->assertSame('2', $formatter->format('2.000000', 'pl'));
        $this->assertSame('2,5', $formatter->format('2.500000', 'pl'));
        $this->assertSame('2.5', $formatter->format('2.500000', 'en'));
        $this->assertSame('1 200', $this->plain($formatter->format('1200.000000', 'pl')));
        $this->assertSame('0,125', $formatter->format('0.125000', 'pl'));
        $this->assertSame('n/a', $formatter->format('n/a', 'pl'));
    }

    public function test_address_lines_use_the_country_name_and_skip_empty_parts(): void
    {
        $formatter = new LocalizedAddressFormatter(new CountryDirectory, new LocalizedPhoneFormatter);
        $address = [
            'recipient_name' => 'Jan Kowalski', 'company_name' => '', 'address_line_1' => 'Testowa 1', 'address_line_2' => null,
            'postal_code' => '00-001', 'city' => 'Warszawa', 'country_code' => 'PL', 'phone' => '+48 500 100 200',
        ];

        $this->assertSame(['Jan Kowalski', 'Testowa 1', '00-001 Warszawa', 'Polska'], $formatter->lines($address, 'pl'));
        $this->assertSame(['Jan Kowalski', 'Testowa 1', '00-001 Warszawa', 'Poland', '+48 500 100 200'], $formatter->lines($address, 'en', true));
        $this->assertSame('Testowa 1, 00-001 Warszawa, Polska', $formatter->inline(['address_line_1' => 'Testowa 1', 'postal_code' => '00-001', 'city' => 'Warszawa', 'country_code' => 'pl'], 'pl'));
    }

    public function test_address_layout_puts_the_postal_code_last_for_us_style_countries(): void
    {
        $formatter = new LocalizedAddressFormatter(new CountryDirectory, new LocalizedPhoneFormatter);

        $this->assertSame(
            ['1 Main St', 'Austin, TX 73301', 'United States'],
            $formatter->lines(['address_line_1' => '1 Main St', 'city' => 'Austin', 'region' => 'TX', 'postal_code' => '73301', 'country_code' => 'US'], 'en'),
        );
        $this->assertSame('XX', $formatter->countryName('XX', 'en'));
    }

    public function test_percentage_uses_locale_separators_and_drops_empty_decimals(): void
    {
        $formatter = new LocalizedPercentageFormatter;

        $this->assertSame('23%', $this->plain($formatter->format('23.000000', 'pl')));
        $this->assertSame('8,5%', $this->plain($formatter->format('8.5', 'pl')));
        $this->assertSame('8.5%', $this->plain($formatter->format('8.5', 'en')));
        $this->assertSame('0%', $this->plain($formatter->format('0', 'en')));
        $this->assertSame('n/a', $formatter->format('n/a', 'en'));
    }

    public function test_phone_is_shown_in_international_notation_when_it_is_a_valid_number(): void
    {
        $formatter = new LocalizedPhoneFormatter;

        $this->assertSame('+48 601 234 567', $formatter->format('601234567', 'PL'));
        $this->assertSame('+48 601 234 567', $formatter->format('+48601234567'));
        $this->assertSame('+48 601 234 567', $formatter->format(' 601-234-567 ', 'pl'));
        $this->assertSame('+1 650-253-0000', $formatter->format('(650) 253-0000', 'US'));
    }

    public function test_an_unrecognised_phone_is_returned_as_typed(): void
    {
        $formatter = new LocalizedPhoneFormatter;

        $this->assertSame('12345', $formatter->format('12345', 'PL'));
        $this->assertSame('ext. 5', $formatter->format('ext. 5'));
        $this->assertSame('601234567', $formatter->format('601234567'));
    }

    public function test_the_address_shows_the_phone_in_international_notation(): void
    {
        $formatter = new LocalizedAddressFormatter(new CountryDirectory, new LocalizedPhoneFormatter);
        $address = ['recipient_name' => 'Jan', 'city' => 'Warszawa', 'country_code' => 'PL', 'phone' => '601234567'];

        $this->assertSame(['Jan', 'Warszawa', 'Polska', '+48 601 234 567'], $formatter->lines($address, 'pl', true));
        $this->assertSame(['Jan', 'Warszawa', 'Polska'], $formatter->lines($address, 'pl'));
    }
}
