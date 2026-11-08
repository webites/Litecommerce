<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

/** Keeps two panel tabs from driving the same update at once. */
interface UpdateRunLock
{
    public function acquire(): bool;

    public function release(): void;
}
