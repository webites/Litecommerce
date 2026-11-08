<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Application;

use Illuminate\Database\Eloquent\Builder;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;

final class PruneAuditEntries
{
    private const IDENTIFIER_KEYS = ['ip', 'ip_address', 'user_agent', 'browser_id'];

    public function __construct(private readonly AuditRetentionRegistry $retention) {}

    public function pruneBatch(\DateTimeImmutable $olderThan, int $batchSize): int
    {
        $query = AuditEntry::query()->where('created_at', '<', $olderThan);

        foreach ($this->retention->indefinitePrefixes() as $prefix) {
            $query->whereRaw('SUBSTR(action, 1, ?) <> ?', [mb_strlen($prefix), $prefix]);
        }

        $ids = $this->ids($query, $batchSize);

        return $ids === [] ? 0 : AuditEntry::query()->whereKey($ids)->delete();
    }

    /** Removes visitor identifiers an extension put into the metadata of entries older than the cutoff; the entry stays. */
    public function scrubIdentifiers(\DateTimeImmutable $olderThan, int $batchSize): int
    {
        $entries = AuditEntry::query()->where('created_at', '<', $olderThan)
            ->where(static function (Builder $query): void {
                foreach (self::IDENTIFIER_KEYS as $key) {
                    $query->orWhere('metadata', 'like', '%"'.$key.'":%');
                }
            })->orderBy('id')->limit($batchSize)->get();

        foreach ($entries as $entry) {
            $entry->update(['metadata' => array_diff_key($entry->metadata ?? [], array_flip(self::IDENTIFIER_KEYS))]);
        }

        return $entries->count();
    }

    /**
     * @param  Builder<AuditEntry>  $query
     * @return list<int>
     */
    private function ids(Builder $query, int $batchSize): array
    {
        return $query->orderBy('id')->limit($batchSize)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
