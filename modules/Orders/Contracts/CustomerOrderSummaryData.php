<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class CustomerOrderSummaryData
{
    public function __construct(
        public string $id,
        public string $status,
        public string $paymentStatus,
        public string $totalGrossAmount,
        public string $currencyCode,
        public ?\DateTimeImmutable $createdAt,
        public int $lineCount,
    ) {}
}
