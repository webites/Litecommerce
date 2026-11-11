<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/** Percentage values as entered ("23", "8.5") rendered with the reader's locale separators and a percent sign. */
interface PercentageFormatter
{
    public function format(string $percentage, ?string $locale = null): string;
}
