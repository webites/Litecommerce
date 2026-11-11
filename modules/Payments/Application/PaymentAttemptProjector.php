<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Litecommerce\Modules\Payments\Contracts\PaymentAttemptData;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class PaymentAttemptProjector
{
    public function __construct(private readonly PaymentMethodRegistry $methods) {}

    public function project(PaymentAttempt $attempt): PaymentAttemptData
    {
        $metadata = $attempt->metadata ?? [];

        return new PaymentAttemptData(
            $attempt->id, $attempt->order_id, $attempt->provider_id, $attempt->method_id,
            $attempt->provider_transaction_id, PaymentAttemptStatus::from($attempt->status),
            (string) $attempt->amount, $attempt->currency_code,
            isset($metadata['redirect_url']) ? (string) $metadata['redirect_url'] : null,
            array_diff_key($metadata, ['redirect_url' => null]),
            ($attempt->method_name ?? '') !== '' ? $attempt->method_name : $this->methods->nameOf($attempt->method_id),
        );
    }
}
