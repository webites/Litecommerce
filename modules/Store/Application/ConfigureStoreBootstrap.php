<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Contracts\StoreBootstrap;

final readonly class ConfigureStoreBootstrap implements StoreBootstrap
{
    public function __construct(private SetDefaultLanguage $language, private SetBaseCurrency $currency) {}

    public function setDefaultLanguage(string $code, string $name): void
    {
        $this->language->handle($code, $name);
    }

    public function setBaseCurrency(string $code, string $name, string $symbol, int $decimalPlaces): void
    {
        $this->currency->handle($code, $name, $symbol, $decimalPlaces);
    }
}
