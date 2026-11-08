<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Litecommerce\Modules\Returns\Infrastructure\Models\RefundRecord;

/**
 * The deliberate, separate "faktycznie wypłacono" step — approving a
 * return never marks money as sent by itself
 * (docs/knowledge-base/06-fulfillment.md#zwroty: "Akcja w panelu emituje
 * event i WYRAŹNIE mówi, że sama nie wysyła pieniędzy"). Idempotent: a
 * repeated call on an already-paid-out record is a no-op.
 */
final class RecordRefundPayout
{
    public function execute(int $refundRecordId, string $reference, string $actorId): void
    {
        RefundRecord::query()->whereKey($refundRecordId)->whereNull('paid_out_at')->update([
            'paid_out_at' => now(), 'payout_reference' => $reference, 'payout_recorded_by' => $actorId,
        ]);
    }
}
