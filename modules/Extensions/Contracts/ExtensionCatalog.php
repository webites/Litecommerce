<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

interface ExtensionCatalog
{
    /** @return list<ExtensionData> */
    public function all(): array;

    public function find(string $id): ?ExtensionData;

    public function isEnabled(string $id): bool;
}
