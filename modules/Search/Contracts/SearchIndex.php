<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

interface SearchIndex
{
    public function ensureReady(): void;

    public function rebuild(): void;

    public function markDirty(): void;

    /** @param list<int> $variantIds */
    public function refreshPrices(array $variantIds): void;

    /** @param list<int> $variantIds */
    public function refreshAvailability(array $variantIds): void;
}
