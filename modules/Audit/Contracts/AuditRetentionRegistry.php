<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Contracts;

/**
 * Audit entries are removed after the retention period unless a module
 * declared their action kind (money, permissions, access) as kept forever.
 */
interface AuditRetentionRegistry
{
    /** Keeps every entry whose action starts with the prefix (e.g. `orders.`) indefinitely. */
    public function keepIndefinitely(string $actionPrefix): void;

    /** @return list<string> */
    public function indefinitePrefixes(): array;
}
