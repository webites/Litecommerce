<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentContext
{
    public function __construct(
        public string $orderId,
        public string $attemptId,
        public string $amount,
        public string $currencyCode,
        public string $mode,
        public string $customerEmail,
        public string $locale,
        public ?string $returnUrl = null,
    ) {}
}
