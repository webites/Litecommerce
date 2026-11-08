<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

interface UpdateStep
{
    public function key(): string;

    /** True once a step starts work that files alone cannot undo (database migrations). */
    public function isIrreversible(): bool;

    /** True when the following steps must run in a fresh request, because this step replaced the code being executed. */
    public function endsRequest(): bool;

    /**
     * Must be safe to repeat after an interruption.
     *
     * @throws UpdateStepFailedException
     */
    public function run(UpdateOperationState $state, UpdateProgress $progress): void;
}
