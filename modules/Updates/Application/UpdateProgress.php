<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

/** Lets a long step persist partial progress (the swap journal) before it finishes. */
interface UpdateProgress
{
    public function persist(UpdateOperationState $state): void;
}
