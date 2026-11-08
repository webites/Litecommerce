<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

interface UpdateInstaller
{
    /**
     * Creates the operation; nothing is changed until advance() runs.
     *
     * @throws UpdateAlreadyRunningException
     */
    public function start(ReleaseInfo $release, ?string $actorId): UpdateOperationData;

    /** Runs pending steps until the time budget is used up, the operation ends or a step fails. */
    public function advance(int $budgetSeconds): ?UpdateOperationData;

    public function current(): ?UpdateOperationData;
}
