<?php

declare(strict_types=1);

namespace Tests\Unit\Store;

use Litecommerce\Modules\Store\Application\LocalizedMoneyFormatter;
use Litecommerce\Modules\Store\Contracts\CurrencyData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use PHPUnit\Framework\TestCase;

final class LocalizedMoneyFormatterTest extends TestCase
{
    private function formatter(): LocalizedMoneyFormatter
    {
        $currencies = [
            'PLN' => new CurrencyData('PLN', 'Złoty', 'zł', 2, true),
            'JPY' => new CurrencyData('JPY', 'Yen', '¥', 0, false),
            'USD' => new CurrencyData('USD', 'Dollar', '$', 2, false),
            'EUR' => new CurrencyData('EUR', 'Euro', '€', 2, false),
        ];

        return new LocalizedMoneyFormatter(new class($currencies) implements CurrencyRepository
        {
            /** @param array<string, CurrencyData> $currencies */
            public function __construct(private readonly array $currencies) {}

            public function base(): ?CurrencyData
            {
                return $this->currencies['PLN'];
            }

            public function find(string $code): ?CurrencyData
            {
                return $this->currencies[$code] ?? null;
            }

            public function all(): array
            {
                return array_values($this->currencies);
            }
        });
    }

    private function plain(string $text): string
    {
        return (string) preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $text);
    }

    public function test_symbol_and_its_position_follow_the_currency_and_the_locale(): void
    {
        $formatter = $this->formatter();

        $this->assertSame('1 234,50 zł', $this->plain($formatter->format('1234.5', 'PLN', 'pl')));
        $this->assertSame('PLN 1,234.50', $this->plain($formatter->format('1234.500000', 'PLN', 'en')));
        $this->assertSame('$100.00', $this->plain($formatter->format('100', 'USD', 'en')));
        $this->assertSame('100,00 €', $this->plain($formatter->format('100', 'EUR', 'pl')));
        $this->assertSame('-9,99 zł', $this->plain($formatter->format('-9.99', 'PLN', 'pl')));
    }

    public function test_decimal_places_come_from_the_currency(): void
    {
        $formatter = $this->formatter();

        $this->assertSame('0,00 zł', $this->plain($formatter->format('0', 'PLN', 'pl')));
        $this->assertSame('¥15', $this->plain($formatter->format('15.000000', 'JPY', 'en')));
    }

    public function test_an_unknown_currency_falls_back_to_the_scale_of_the_amount(): void
    {
        $formatter = $this->formatter();

        $this->assertSame('XTS 79', $this->plain($formatter->format('79.000000', 'XTS', 'en')));
        $this->assertSame('XTS 79.5', $this->plain($formatter->format('79.500000', 'XTS', 'en')));
    }

    public function test_annotate_adds_a_formatted_sibling_to_every_amount_at_any_depth(): void
    {
        $annotated = $this->formatter()->annotate([
            'currency_code' => 'PLN',
            'total_gross_amount' => '79.00',
            'quantity' => '2',
            'lines' => [['unit_amount' => '10.5', 'label' => 'x', 'note_amount' => null]],
            'delivery' => ['selected' => ['gross_amount' => '0.00']],
        ], 'PLN', 'en');

        $this->assertSame('PLN 79.00', $this->plain($annotated['total_gross_amount_formatted']));
        $this->assertSame('PLN 10.50', $this->plain($annotated['lines'][0]['unit_amount_formatted']));
        $this->assertSame('PLN 0.00', $this->plain($annotated['delivery']['selected']['gross_amount_formatted']));
        $this->assertArrayNotHasKey('quantity_formatted', $annotated);
        $this->assertArrayNotHasKey('note_amount_formatted', $annotated['lines'][0]);
        $this->assertSame('79.00', $annotated['total_gross_amount']);
    }
}
