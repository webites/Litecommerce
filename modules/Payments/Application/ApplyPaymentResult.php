<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Payments\Contracts\Events\PaymentAttemptFailed;
use Litecommerce\Modules\Payments\Contracts\Events\PaymentConfirmed;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptData;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class ApplyPaymentResult
{
    public function __construct(
        private readonly OrderPaymentManager $orders,
        private readonly PaymentAttemptProjector $projector,
    ) {}

    public function execute(string $attemptId, PaymentStatusResult $result): PaymentAttemptData
    {
        return DB::transaction(function () use ($attemptId, $result): PaymentAttemptData {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if (in_array($attempt->status, [PaymentAttemptStatus::Succeeded->value, PaymentAttemptStatus::LatePayment->value, PaymentAttemptStatus::Overpayment->value], true)) {
                return $this->projector->project($attempt);
            }

            if ($result->status === PaymentAttemptStatus::Succeeded) {
                $paidAt = $result->paidAt ?? new \DateTimeImmutable;
                $outcome = $this->orders->confirm($attempt->order_id, $paidAt, $attempt->id);
                $status = match ($outcome) {
                    'late_payment' => PaymentAttemptStatus::LatePayment,
                    'overpayment' => PaymentAttemptStatus::Overpayment,
                    default => PaymentAttemptStatus::Succeeded,
                };
                $attempt->update(['status' => $status->value, 'paid_at' => $paidAt]);
                DB::afterCommit(fn () => CommittedEvent::dispatch(new PaymentConfirmed($attempt->id, $attempt->order_id, $attempt->amount, $attempt->currency_code)));
            } elseif (in_array($result->status, [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled], true)) {
                $attempt->update([
                    'status' => $result->status->value, 'failure_code' => $result->failureCode,
                    'failure_message' => $result->failureMessage, 'last_checked_at' => now(),
                ]);
                DB::afterCommit(fn () => CommittedEvent::dispatch(new PaymentAttemptFailed($attempt->id, $attempt->order_id, $result->failureCode, $result->failureMessage)));
            } else {
                $attempt->update(['status' => $result->status->value, 'last_checked_at' => now()]);
            }

            return $this->projector->project($attempt->fresh());
        });
    }
}
