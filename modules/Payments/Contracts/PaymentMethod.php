<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentMethod
{
    public function descriptor(): PaymentMethodDescriptor;

    public function available(PaymentAvailabilityContext $context): bool;

    public function start(PaymentContext $context): PaymentStartResult;

    /** @param array<string, string> $headers */
    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification;

    public function query(string $providerTransactionId): PaymentStatusResult;

    public function cancel(string $providerTransactionId): void;
}
