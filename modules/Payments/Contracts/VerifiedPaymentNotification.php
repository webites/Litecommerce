<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class VerifiedPaymentNotification
{
    /** @param array<string, mixed> $sanitizedPayload */
    public function __construct(
        public string $providerTransactionId,
        public PaymentAttemptStatus $status,
        public ?\DateTimeImmutable $paidAt,
        public ?string $externalNotificationId = null,
        public array $sanitizedPayload = [],
    ) {}
}
