<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

interface WarehouseProvisioner
{
    /**
     * Creates the store's single warehouse if it doesn't exist yet.
     * Idempotent — safe to call repeatedly (e.g. a retried install finalize step).
     */
    public function ensureDefaultWarehouseExists(): void;
}
