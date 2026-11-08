<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Contracts;

interface CacheMaintenance
{
    public function clearApplicationCache(): void;

    public function status(): CacheStatusData;
}
