<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use DateTimeInterface;
use IntlDateFormatter;
use Litecommerce\Modules\Store\Contracts\DateTimeFormatter;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

final class LocalizedDateTimeFormatter implements DateTimeFormatter
{
    /** @var array<string, IntlDateFormatter> */
    private array $formatters = [];

    public function __construct(private readonly StoreSettingsReader $settings) {}

    public function date(DateTimeInterface $moment, ?string $locale = null): string
    {
        return $this->formatterFor($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE)->format($moment);
    }

    public function dateTime(DateTimeInterface $moment, ?string $locale = null): string
    {
        return $this->formatterFor($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT)->format($moment);
    }

    private function formatterFor(?string $locale, int $dateType, int $timeType): IntlDateFormatter
    {
        $locale ??= app()->getLocale();
        $timezone = $this->settings->current()->timezone;
        $key = $locale.'|'.$timezone.'|'.$dateType.'|'.$timeType;

        return $this->formatters[$key] ??= new IntlDateFormatter($locale, $dateType, $timeType, $timezone);
    }
}
