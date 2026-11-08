<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class OrderPaymentData
{
    public function __construct(
        public string $id,
        public string $status,
        public string $paymentStatus,
        public string $paymentMethodId,
        public string $amount,
        public string $currencyCode,
        public string $mode,
        public string $locale,
        public int $version,
        public ?\DateTimeImmutable $deadlineAt,
    ) {}
}
