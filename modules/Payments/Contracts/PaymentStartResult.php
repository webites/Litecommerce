<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentStartResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $providerTransactionId,
        public PaymentAttemptStatus $status,
        public ?string $redirectUrl = null,
        public array $metadata = [],
    ) {}
}
