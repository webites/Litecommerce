<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

final readonly class RefundRecordData
{
    public function __construct(
        public int $id,
        public string $orderId,
        public string $currencyCode,
        public string $productsNetAmount,
        public string $productsVatAmount,
        public string $productsGrossAmount,
        public string $couponRestoredAmount,
        public ?string $deliveryNetAmount,
        public ?string $deliveryVatAmount,
        public ?string $deliveryGrossAmount,
        public string $moneyRefundAmount,
        public string $decidedBy,
        public \DateTimeImmutable $decidedAt,
        public ?\DateTimeImmutable $paidOutAt,
        public ?string $payoutReference,
    ) {}
}
