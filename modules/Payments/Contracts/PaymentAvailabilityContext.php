<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentAvailabilityContext
{
    public function __construct(
        public string $checkoutReference,
        public string $amount,
        public string $currencyCode,
        public bool $containsPhysicalProducts,
        public bool $containsDigitalProducts,
        public ?int $customerAccountId,
        /**
         * The delivery method key chosen on the checkout draft, if
         * any — cash-on-delivery and pay-on-pickup gate themselves on this
         * through Delivery\Contracts\DeliveryMethodCatalog, per "COD
         * wymaga zdolności dostawy do pobrania" (06-fulfillment.md).
         */
        public ?string $selectedDeliveryMethodId = null,
    ) {}

    /**
     * True when coupons already cover the entire order — "Pokrycie całości
     * pomija operatora płatności" (04-pricing-coupons.md). Every other
     * built-in method gates on this being false, since only
     * core.coupon_full_coverage may be selected in that case.
     */
    public function isFullyCovered(): bool
    {
        return bccomp($this->amount, '0', 6) === 0;
    }
}
