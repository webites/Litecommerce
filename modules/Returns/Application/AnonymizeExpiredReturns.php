<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest;

/** Removes the requester's e-mail and free-text comment once the order's retention period ended; lines and refunds stay. */
final class AnonymizeExpiredReturns
{
    public function anonymizeBatch(\DateTimeImmutable $createdBefore, int $batchSize): int
    {
        $ids = ReturnRequest::query()->whereNull('anonymized_at')->where('created_at', '<', $createdBefore)
            ->orderBy('created_at')->limit($batchSize)->pluck('id')->all();

        foreach ($ids as $id) {
            ReturnRequest::query()->whereKey($id)->update([
                'email' => 'anonymized-'.substr((string) $id, 0, 8).'@anonymized.invalid',
                'comment' => null, 'customer_account_id' => null, 'anonymized_at' => now(),
            ]);
        }

        return count($ids);
    }
}
