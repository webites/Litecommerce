<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/** Decimal quantities (stored as decimal(20,6) strings) without trailing zeros and with locale separators. */
interface QuantityFormatter
{
    public function format(string $quantity, ?string $locale = null): string;
}
