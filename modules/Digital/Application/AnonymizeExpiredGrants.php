<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;

/** Clears the buyer's e-mail from grants once the retention period of the purchase ended; the grant itself stays. */
final class AnonymizeExpiredGrants
{
    public function anonymizeBatch(\DateTimeImmutable $grantedBefore, int $batchSize): int
    {
        $ids = DownloadGrant::query()->whereNotNull('email')->where('granted_at', '<', $grantedBefore)
            ->orderBy('id')->limit($batchSize)->pluck('id')->all();

        return $ids === [] ? 0 : DownloadGrant::query()->whereKey($ids)->update(['email' => null, 'customer_account_id' => null]);
    }
}
