<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

/** Optional capability implemented only by methods that can issue links. */
interface PaymentLinkCapability
{
    public function createPaymentLink(PaymentContext $context, \DateTimeImmutable $expiresAt): PaymentStartResult;
}
