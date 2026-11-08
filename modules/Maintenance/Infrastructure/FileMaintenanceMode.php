<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Infrastructure;

use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceState;

final class FileMaintenanceMode implements MaintenanceMode
{
    public static function path(): string
    {
        return storage_path('maintenance.json');
    }

    public function state(): ?MaintenanceState
    {
        $raw = @file_get_contents(self::path());
        $data = $raw === false ? null : json_decode($raw, true);

        if (! is_array($data)) {
            return null;
        }

        return new MaintenanceState(
            MaintenanceReason::tryFrom((string) ($data['reason'] ?? '')) ?? MaintenanceReason::Manual,
            new \DateTimeImmutable((string) ($data['enabled_at'] ?? 'now')),
            isset($data['message']) ? (string) $data['message'] : null,
            max(1, (int) ($data['retry_after_seconds'] ?? config('maintenance.retry_after_seconds', 300))),
            isset($data['actor_id']) ? (string) $data['actor_id'] : null,
        );
    }

    public function isActive(): bool
    {
        return file_exists(self::path());
    }

    public function enable(MaintenanceReason $reason, ?string $message = null, ?string $actorId = null, ?int $retryAfterSeconds = null): bool
    {
        $payload = json_encode([
            'reason' => $reason->value,
            'enabled_at' => now()->toIso8601String(),
            'message' => $message === null || trim($message) === '' ? null : trim($message),
            'retry_after_seconds' => max(1, $retryAfterSeconds ?? (int) config('maintenance.retry_after_seconds', 300)),
            'actor_id' => $actorId,
        ], JSON_THROW_ON_ERROR);

        $handle = @fopen(self::path(), 'x');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, $payload);
        fclose($handle);

        return true;
    }

    public function disable(): bool
    {
        return @unlink(self::path());
    }
}
