<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class PlaceOrderData
{
    /**
     * @param  array<string, mixed>  $billingAddress
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  list<OrderPlacementLineData>  $lines
     * @param  array<string, mixed>|null  $deliveryPickupPointSnapshot  Present only for a pickup delivery — name, address and instructions as they were at order time.
     * @param  list<array{code:string,base_amount:string,product_gross_amount:string,delivery_gross_amount:string}>  $couponUsages  One
     *                                                                                                                              entry per distinct code actually spent on this order (products and/or
     *                                                                                                                              delivery combined) — base_amount is what gets reserved against the
     *                                                                                                                              code's balance; the two gross amounts are display-only, in order currency.
     */
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public ?int $customerAccountId,
        public string $buyerType,
        public string $email,
        public ?string $companyName,
        public ?string $taxIdentifier,
        public ?string $registrationNumber,
        public ?string $phone,
        public ?string $customerNotes,
        public array $billingAddress,
        public ?array $shippingAddress,
        public string $locale,
        public string $currencyCode,
        public string $paymentMethodId,
        public string $paymentKind,
        public string $totalNetAmount,
        public string $totalVatAmount,
        public string $totalGrossAmount,
        public array $lines,
        public bool $testMode = false,
        public ?string $deliveryMethodId = null,
        public ?string $deliveryKind = null,
        public ?string $deliveryName = null,
        public ?string $deliveryNetAmount = null,
        public ?string $deliveryVatAmount = null,
        public ?string $deliveryGrossAmount = null,
        public ?int $deliveryPickupPointId = null,
        public ?array $deliveryPickupPointSnapshot = null,
        public array $couponUsages = [],
    ) {}
}
