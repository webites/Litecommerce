<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\UpdateAlreadyRunningException;
use Litecommerce\Modules\Updates\Contracts\UpdateInstaller;
use Litecommerce\Modules\Updates\Contracts\UpdateOperationData;
use Throwable;

/**
 * Drives the update steps in order with persistent progress. A failure before an irreversible step restores
 * the replaced files and leaves maintenance; after one, the operation stops as failed and stays in maintenance.
 */
final class RunUpdate implements UpdateInstaller
{
    /** @param list<UpdateStep> $steps */
    public function __construct(
        private readonly array $steps,
        private readonly UpdateOperationStore $store,
        private readonly FileSwapper $swapper,
        private readonly UpdatePaths $paths,
        private readonly MaintenanceMode $maintenance,
        private readonly UpdateRunLock $lock,
    ) {}

    public function start(ReleaseInfo $release, ?string $actorId): UpdateOperationData
    {
        $existing = $this->store->load();

        if ($existing !== null && ! $existing->isFinished()) {
            throw new UpdateAlreadyRunningException('An update is already in progress.');
        }

        $state = new UpdateOperationState(bin2hex(random_bytes(8)), $release->version->toString(), $release->toArray(), $actorId, gmdate('c'));
        foreach ($this->steps as $step) {
            $state->mark($step->key(), 'pending');
        }
        $this->store->save($state);

        return $this->present($state);
    }

    public function advance(int $budgetSeconds): ?UpdateOperationData
    {
        $state = $this->store->load();

        if ($state === null || $state->status !== UpdateOperationState::RUNNING) {
            return $state === null ? null : $this->present($state);
        }

        if (! $this->lock->acquire()) {
            return $this->present($state);
        }

        try {
            return $this->drive($state, $budgetSeconds);
        } finally {
            $this->lock->release();
        }
    }

    private function drive(UpdateOperationState $state, int $budgetSeconds): UpdateOperationData
    {
        $deadline = microtime(true) + $budgetSeconds;
        $progress = new class($this->store) implements UpdateProgress
        {
            public function __construct(private readonly UpdateOperationStore $store) {}

            public function persist(UpdateOperationState $state): void
            {
                $this->store->save($state);
            }
        };

        foreach ($this->steps as $step) {
            if ($state->isDone($step->key())) {
                continue;
            }

            if (microtime(true) >= $deadline) {
                return $this->present($state);
            }

            if (! $this->runStep($step, $state, $progress) || $step->endsRequest()) {
                return $this->present($state);
            }
        }

        $state->status = UpdateOperationState::COMPLETED;
        $state->finishedAt = gmdate('c');
        $this->store->save($state);

        return $this->present($state);
    }

    public function current(): ?UpdateOperationData
    {
        $state = $this->store->load();

        return $state === null ? null : $this->present($state);
    }

    private function runStep(UpdateStep $step, UpdateOperationState $state, UpdateProgress $progress): bool
    {
        if ($step->isIrreversible()) {
            $state->irreversible = true;
        }

        $state->mark($step->key(), 'running');
        $this->store->save($state);

        try {
            $step->run($state, $progress);
        } catch (UpdateStepFailedException $exception) {
            $this->fail($state, $step->key(), $exception->errorCode, $exception->getMessage());

            return false;
        } catch (Throwable $exception) {
            $this->fail($state, $step->key(), 'unexpected_error', $exception->getMessage());

            return false;
        }

        $state->mark($step->key(), 'done');
        $this->store->save($state);

        return true;
    }

    private function fail(UpdateOperationState $state, string $stepKey, string $code, string $message): void
    {
        $state->mark($stepKey, 'failed', $message);
        $state->errorCode = $code;
        $state->errorMessage = $message;
        $state->finishedAt = gmdate('c');
        $state->status = UpdateOperationState::FAILED;

        if (! $state->irreversible && $this->rollBack($state)) {
            $state->status = UpdateOperationState::ROLLED_BACK;
        }

        $this->store->save($state);
    }

    private function rollBack(UpdateOperationState $state): bool
    {
        try {
            $this->swapper->restore($this->paths, $state);
        } catch (Throwable $exception) {
            $state->errorMessage .= ' | restore failed: '.$exception->getMessage();

            return false;
        }

        if ($state->maintenanceEnabledByUpdate) {
            $this->maintenance->disable();
        }

        return true;
    }

    private function present(UpdateOperationState $state): UpdateOperationData
    {
        return new UpdateOperationData(
            $state->id, $state->targetVersion, $state->status, $state->steps,
            $state->errorCode, $state->errorMessage, $state->startedAt, $state->finishedAt,
        );
    }
}
