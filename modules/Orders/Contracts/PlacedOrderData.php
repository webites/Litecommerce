<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class PlacedOrderData
{
    public function __construct(
        public string $id,
        public string $number,
        public string $status,
        public string $paymentStatus,
        public string $paymentMethodId,
        public string $amount,
        public string $currencyCode,
        public ?\DateTimeImmutable $paymentDeadlineAt,
    ) {}
}
