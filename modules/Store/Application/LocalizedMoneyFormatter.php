<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\MoneyFormatter;
use NumberFormatter;

final class LocalizedMoneyFormatter implements MoneyFormatter
{
    private const MAX_PLACES = 6;

    /** @var array<string, int>|null */
    private ?array $placesByCurrency = null;

    /** @var array<string, NumberFormatter> */
    private array $formatters = [];

    public function __construct(private readonly CurrencyRepository $currencies) {}

    public function format(string $amount, string $currencyCode, ?string $locale = null): string
    {
        $places = $this->placesByCurrency()[strtoupper($currencyCode)] ?? $this->scaleOf($amount);

        return $this->formatWithPlaces($amount, $currencyCode, $places, $locale);
    }

    public function formatWithPlaces(string $amount, string $currencyCode, int $decimalPlaces, ?string $locale = null): string
    {
        $decimalPlaces = max(0, min(self::MAX_PLACES, $decimalPlaces));
        $rounded = bcadd($amount, '0', $decimalPlaces);
        $formatter = $this->formatterFor($locale ?? app()->getLocale(), $decimalPlaces);
        $formatted = $formatter->formatCurrency((float) $rounded, strtoupper($currencyCode));

        return $formatted === false ? $rounded.' '.$currencyCode : $formatted;
    }

    public function annotate(array $payload, string $currencyCode, ?string $locale = null): array
    {
        $annotated = [];
        foreach ($payload as $key => $value) {
            $annotated[$key] = is_array($value) ? $this->annotate($value, $currencyCode, $locale) : $value;

            if (is_string($key) && str_ends_with($key, '_amount') && is_string($value) && is_numeric($value)) {
                $annotated[$key.'_formatted'] = $this->format($value, $currencyCode, $locale);
            }
        }

        return $annotated;
    }

    private function formatterFor(string $locale, int $decimalPlaces): NumberFormatter
    {
        $key = $locale.'|'.$decimalPlaces;

        if (! isset($this->formatters[$key])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimalPlaces);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimalPlaces);
            $this->formatters[$key] = $formatter;
        }

        return $this->formatters[$key];
    }

    /** @return array<string, int> */
    private function placesByCurrency(): array
    {
        if ($this->placesByCurrency === null) {
            $this->placesByCurrency = [];
            foreach ($this->currencies->all() as $currency) {
                $this->placesByCurrency[strtoupper($currency->code)] = $currency->decimalPlaces;
            }
        }

        return $this->placesByCurrency;
    }

    private function scaleOf(string $amount): int
    {
        return str_contains($amount, '.') ? strlen(rtrim(substr($amount, (int) strpos($amount, '.') + 1), '0')) : 0;
    }
}
