<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadAttempt;

/** Clears the visitor's IP address and browser identifier from old attempts; the attempt itself stays. */
final class ScrubDownloadAttemptIdentifiers
{
    public function scrubBatch(\DateTimeImmutable $olderThan, int $batchSize): int
    {
        $ids = DownloadAttempt::query()
            ->where('occurred_at', '<', $olderThan)
            ->where(static fn ($query) => $query->whereNotNull('ip_address')->orWhereNotNull('user_agent'))
            ->orderBy('id')->limit($batchSize)->pluck('id')->all();

        return $ids === [] ? 0 : DownloadAttempt::query()->whereKey($ids)->update(['ip_address' => null, 'user_agent' => null]);
    }
}
