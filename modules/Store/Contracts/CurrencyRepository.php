<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface CurrencyRepository
{
    public function base(): ?CurrencyData;

    public function find(string $code): ?CurrencyData;

    /**
     * @return list<CurrencyData>
     */
    public function all(): array;
}
