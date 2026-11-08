<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Contracts;

final readonly class CacheStatusData
{
    public function __construct(
        public string $store,
        public bool $available,
        public bool $supportsLocks,
        public bool $supportsTags,
    ) {}
}
