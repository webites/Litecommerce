<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateOperationStore;

final class FileUpdateOperationStore implements UpdateOperationStore
{
    public static function path(): string
    {
        return storage_path('app/updates/operation.json');
    }

    public function load(): ?UpdateOperationState
    {
        $raw = @file_get_contents(self::path());
        $d = $raw === false ? null : json_decode($raw, true);

        if (! is_array($d) || ! isset($d['id'], $d['target_version'], $d['release'], $d['started_at'])) {
            return null;
        }

        return new UpdateOperationState(
            (string) $d['id'], (string) $d['target_version'], (array) $d['release'],
            isset($d['actor_id']) ? (string) $d['actor_id'] : null, (string) $d['started_at'],
            (string) ($d['status'] ?? UpdateOperationState::RUNNING), (array) ($d['steps'] ?? []), (bool) ($d['irreversible'] ?? false),
            (bool) ($d['maintenance_enabled_by_update'] ?? false), (array) ($d['journal'] ?? []),
            isset($d['error_code']) ? (string) $d['error_code'] : null, isset($d['error_message']) ? (string) $d['error_message'] : null,
            isset($d['finished_at']) ? (string) $d['finished_at'] : null, isset($d['database_backup']) ? (string) $d['database_backup'] : null,
        );
    }

    public function save(UpdateOperationState $state): void
    {
        $directory = dirname(self::path());

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('The update progress file cannot be created.');
        }

        $temporary = self::path().'.tmp';
        file_put_contents($temporary, json_encode([
            'id' => $state->id, 'target_version' => $state->targetVersion, 'release' => $state->release, 'actor_id' => $state->actorId,
            'started_at' => $state->startedAt, 'status' => $state->status, 'steps' => $state->steps, 'irreversible' => $state->irreversible,
            'maintenance_enabled_by_update' => $state->maintenanceEnabledByUpdate, 'journal' => $state->journal,
            'error_code' => $state->errorCode, 'error_message' => $state->errorMessage, 'finished_at' => $state->finishedAt,
            'database_backup' => $state->databaseBackup,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
        rename($temporary, self::path());
    }
}
