<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

interface DemoDataManager
{
    public function install(?string $locale = null): DemoDataBatchData;

    public function remove(?string $batchId = null): DemoDataBatchData;

    public function active(): ?DemoDataBatchData;

    /** @return list<DemoDataBatchData> */
    public function all(): array;
}
