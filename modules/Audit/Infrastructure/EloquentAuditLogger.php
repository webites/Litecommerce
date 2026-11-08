<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Infrastructure;

use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;

final class EloquentAuditLogger implements AuditLogger
{
    private const SENSITIVE_KEY_PARTS = [
        'authorization',
        'cookie',
        'password',
        'recovery_code',
        'secret',
        'token',
    ];

    public function record(AuditEntryData $entry): void
    {
        AuditEntry::query()->create([
            'actor_type' => $entry->actorType,
            'actor_id' => $entry->actorId,
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'changes' => $this->redact($entry->changes),
            'metadata' => $this->redact($entry->metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitive($normalizedKey)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }
}
