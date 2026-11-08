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

/**
 * "płatność na miejscu jest oddzielna i wymaga odbioru osobistego"
 * (06-fulfillment.md) — only available when the checkout's selected
 * delivery configuration is a pickup point that the merchant has marked
 * as accepting payment on collection.
 */
final class CorePayOnPickupMethod implements PaymentMethod
{
    public function __construct(private readonly DeliveryMethodCatalog $delivery) {}

    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor('core.pay_on_pickup', 'core', trans('payments::methods.pay_on_pickup'), 'pay_on_pickup');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return ! $context->isFullyCovered()
            && $context->selectedDeliveryMethodId !== null
            && $this->delivery->supportsPayOnPickup($context->selectedDeliveryMethodId);
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        return new PaymentStartResult('pay-on-pickup:'.$context->orderId, PaymentAttemptStatus::Pending);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        throw new LogicException('Pay on pickup is confirmed manually and has no webhook.');
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Pending);
    }

    public function cancel(string $providerTransactionId): void {}
}
