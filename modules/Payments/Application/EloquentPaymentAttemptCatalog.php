<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class EloquentPaymentAttemptCatalog implements PaymentAttemptCatalog
{
    public function __construct(private readonly PaymentAttemptProjector $projector) {}

    public function forOrder(string $orderId): array
    {
        return PaymentAttempt::query()->where('order_id', $orderId)->orderByDesc('created_at')->get()
            ->map(fn (PaymentAttempt $attempt) => $this->projector->project($attempt))->all();
    }
}
