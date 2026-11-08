<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

interface DemoDataContributor
{
    public function key(): string;

    public function priority(): int;

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void;

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void;
}
