<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts\Events;

/**
 * Fired synchronously, in the same request, after one or more variants'
 * stock levels change (receipt, correction, reservation release). A
 * plugin listens via Laravel's native dispatcher from its own boot():
 * `Event::listen(StockLevelChanged::class, ...)` — a natural hook for a
 * low-stock/back-in-stock notification. Carries only variant ids; a
 * listener reads the current level back via `StockLevelCatalog::find()`/
 * `findMany()`, never this module's Eloquent models.
 */
final readonly class StockLevelChanged
{
    /** @param list<int> $variantIds */
    public function __construct(public array $variantIds) {}
}
