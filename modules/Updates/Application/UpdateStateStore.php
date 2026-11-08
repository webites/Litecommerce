<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

interface UpdateStateStore
{
    public function load(): UpdateState;

    public function save(UpdateState $state): void;
}
