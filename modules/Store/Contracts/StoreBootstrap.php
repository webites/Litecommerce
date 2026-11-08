<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/** First-time store setup: the default language and the base currency. */
interface StoreBootstrap
{
    public function setDefaultLanguage(string $code, string $name): void;

    public function setBaseCurrency(string $code, string $name, string $symbol, int $decimalPlaces): void;
}
