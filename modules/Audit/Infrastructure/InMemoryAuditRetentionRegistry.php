<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Infrastructure;

use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;

final class InMemoryAuditRetentionRegistry implements AuditRetentionRegistry
{
    /** @var array<string, true> */
    private array $prefixes = [];

    public function keepIndefinitely(string $actionPrefix): void
    {
        if ($actionPrefix === '') {
            throw new \InvalidArgumentException('An audit retention prefix must not be empty.');
        }

        $this->prefixes[$actionPrefix] = true;
    }

    public function indefinitePrefixes(): array
    {
        return array_keys($this->prefixes);
    }
}
