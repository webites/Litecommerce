<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Contracts\PercentageFormatter;
use NumberFormatter;

final class LocalizedPercentageFormatter implements PercentageFormatter
{
    private const MAX_PLACES = 2;

    /** @var array<string, NumberFormatter> */
    private array $formatters = [];

    public function format(string $percentage, ?string $locale = null): string
    {
        if (! is_numeric($percentage)) {
            return $percentage;
        }

        $formatted = $this->formatterFor($locale ?? app()->getLocale())->format((float) $percentage / 100);

        return $formatted === false ? $percentage.'%' : $formatted;
    }

    private function formatterFor(string $locale): NumberFormatter
    {
        if (! isset($this->formatters[$locale])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, self::MAX_PLACES);
            $this->formatters[$locale] = $formatter;
        }

        return $this->formatters[$locale];
    }
}
