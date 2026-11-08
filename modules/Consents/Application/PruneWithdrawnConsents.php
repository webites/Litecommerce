<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Consents\Infrastructure\Models\MarketingConsent;

/**
 * Deletes the proof of a consent (with its event log) once the consent has
 * been withdrawn for longer than the retention period. An active consent is
 * never removed by age.
 */
final class PruneWithdrawnConsents
{
    public function pruneBatch(\DateTimeImmutable $now, int $years, int $batchSize): int
    {
        return DB::transaction(function () use ($now, $years, $batchSize): int {
            $ids = MarketingConsent::query()
                ->where('status', MarketingConsent::WITHDRAWN)
                ->where('withdrawn_at', '<', $now->modify("-{$years} years"))
                ->orderBy('id')->limit($batchSize)->lockForUpdate()->pluck('id')->all();

            return $ids === [] ? 0 : MarketingConsent::query()->whereKey($ids)->delete();
        });
    }
}
