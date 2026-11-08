<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Litecommerce\Modules\Updates\Application\UpdateState;
use Litecommerce\Modules\Updates\Application\UpdateStateStore;

final class FileUpdateStateStore implements UpdateStateStore
{
    public static function path(): string
    {
        return storage_path('app/updates/state.json');
    }

    public function load(): UpdateState
    {
        $raw = @file_get_contents(self::path());
        $data = $raw === false ? null : json_decode($raw, true);

        if (! is_array($data)) {
            return new UpdateState;
        }

        return new UpdateState(
            (bool) ($data['automatic_checks'] ?? true),
            isset($data['checked_at']) ? (string) $data['checked_at'] : null,
            isset($data['error_code']) ? (string) $data['error_code'] : null,
            is_array($data['release'] ?? null) ? $data['release'] : null,
            is_array($data['problems'] ?? null) ? array_values($data['problems']) : [],
        );
    }

    public function save(UpdateState $state): void
    {
        $directory = dirname(self::path());

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return;
        }

        file_put_contents(self::path(), json_encode([
            'automatic_checks' => $state->automaticChecks,
            'checked_at' => $state->checkedAt,
            'error_code' => $state->errorCode,
            'release' => $state->release,
            'problems' => $state->problems,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
