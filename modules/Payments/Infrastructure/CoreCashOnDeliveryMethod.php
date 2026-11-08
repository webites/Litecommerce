<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure;

use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethod;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentStartResult;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Contracts\VerifiedPaymentNotification;
use LogicException;

// Only available when the selected delivery method declares COD support; collected by the carrier, confirmed manually by staff.
final class CoreCashOnDeliveryMethod implements PaymentMethod
{
    public function __construct(private readonly DeliveryMethodCatalog $delivery) {}

    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor('core.cash_on_delivery', 'core', trans('payments::methods.cash_on_delivery'), 'cash_on_delivery');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return ! $context->isFullyCovered()
            && $context->selectedDeliveryMethodId !== null
            && $this->delivery->supportsCashOnDelivery($context->selectedDeliveryMethodId);
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        return new PaymentStartResult('cod:'.$context->orderId, PaymentAttemptStatus::Pending);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        throw new LogicException('Cash on delivery is confirmed manually and has no webhook.');
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Pending);
    }

    public function cancel(string $providerTransactionId): void {}
}
