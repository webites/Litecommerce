<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

final readonly class ReturnRequestData
{
    /**
     * @param  list<ReturnLineData>  $lines
     * @param  list<RefundRecordData>  $refunds
     */
    public function __construct(
        public string $id,
        public string $orderId,
        public string $source,
        public ?int $customerAccountId,
        public string $email,
        public ?string $comment,
        public string $status,
        public \DateTimeImmutable $createdAt,
        public array $lines,
        public array $refunds,
    ) {}
}
