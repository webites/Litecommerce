<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface StoreSettingsReader
{
    public function current(): StoreSettingsData;
}
