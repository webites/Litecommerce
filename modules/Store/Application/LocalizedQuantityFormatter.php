<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Contracts\QuantityFormatter;
use NumberFormatter;

final class LocalizedQuantityFormatter implements QuantityFormatter
{
    private const MAX_PLACES = 6;

    /** @var array<string, NumberFormatter> */
    private array $formatters = [];

    public function format(string $quantity, ?string $locale = null): string
    {
        if (! is_numeric($quantity)) {
            return $quantity;
        }

        $formatted = $this->formatterFor($locale ?? app()->getLocale())->format((float) $quantity);

        return $formatted === false ? $quantity : $formatted;
    }

    private function formatterFor(string $locale): NumberFormatter
    {
        if (! isset($this->formatters[$locale])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, self::MAX_PLACES);
            $this->formatters[$locale] = $formatter;
        }

        return $this->formatters[$locale];
    }
}
