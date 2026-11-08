<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentStatusResult
{
    public function __construct(
        public PaymentAttemptStatus $status,
        public ?\DateTimeImmutable $paidAt = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
