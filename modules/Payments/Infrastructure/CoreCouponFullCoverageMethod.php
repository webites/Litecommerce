<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure;

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
 * "Pokrycie całości pomija operatora płatności i kieruje zamówienie do
 * realizacji" (04-pricing-coupons.md) — the only method available when
 * coupons already bring the total to zero, and unavailable otherwise.
 * kind='free' tells PlaceOrder to mark the order paid immediately and skip
 * StartPaymentAttempt entirely (it already no-ops for a zero amount order
 * regardless of method, see Payments\Application\StartPaymentAttempt).
 * No dependency on Coupons — a zero total is a sufficient signal on its
 * own, kept free of any cross-module coupling.
 */
final class CoreCouponFullCoverageMethod implements PaymentMethod
{
    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor('core.coupon_full_coverage', 'core', trans('payments::methods.coupon_full_coverage'), 'free');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return $context->isFullyCovered();
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        return new PaymentStartResult('coupon:'.$context->orderId, PaymentAttemptStatus::Succeeded);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        throw new LogicException('Coupon full coverage settles at order placement and has no webhook.');
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Succeeded);
    }

    public function cancel(string $providerTransactionId): void {}
}
