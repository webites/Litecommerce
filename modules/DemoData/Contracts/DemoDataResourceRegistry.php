<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

interface DemoDataResourceRegistry
{
    public function record(string $batchId, string $contributor, string $resourceType, int|string $resourceId): void;

    /** @return list<string> */
    public function ids(string $batchId, string $contributor, string $resourceType): array;
}
