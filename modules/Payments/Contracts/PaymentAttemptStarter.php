<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentAttemptStarter
{
    public function start(string $orderId, string $customerEmail, ?string $returnUrl = null): ?PaymentAttemptData;

    public function retry(string $orderId, string $customerEmail, ?string $returnUrl = null): PaymentAttemptData;
}
