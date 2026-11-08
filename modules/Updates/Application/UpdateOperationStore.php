<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

/** Progress lives outside the database so it survives the schema changes an update performs. */
interface UpdateOperationStore
{
    public function load(): ?UpdateOperationState;

    public function save(UpdateOperationState $state): void;
}
