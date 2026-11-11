<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class OrderReturnableData
{
    /** @param  list<OrderLineDetailData>  $lines */
    public function __construct(
        public string $id,
        public string $number,
        public string $currencyCode,
        public string $paymentStatus,
        public ?\DateTimeImmutable $paidAt,
        public string $mode,
        public string $locale,
        public ?int $customerAccountId,
        public string $email,
        public ?string $deliveryNetAmount,
        public ?string $deliveryVatAmount,
        public ?string $deliveryGrossAmount,
        public array $lines,
    ) {}
}
