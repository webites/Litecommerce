<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

use DateTimeInterface;

/**
 * Customer- and staff-facing dates in the store time zone and the reader's locale.
 * Stored timestamps stay in UTC; only presentation converts them.
 */
interface DateTimeFormatter
{
    public function date(DateTimeInterface $moment, ?string $locale = null): string;

    public function dateTime(DateTimeInterface $moment, ?string $locale = null): string;
}
