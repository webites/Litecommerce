<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * A full order receipt — everything a storefront confirmation page or a
 * customer's "my orders" detail page needs, without exposing the Eloquent
 * model. Unlike OrderPaymentData/OrderReturnableData (each built for one
 * narrow internal use), this is the "give me the whole thing" read.
 */
final readonly class OrderDetailData
{
    /**
     * @param  array<string, mixed>  $billingAddress
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $deliveryPickupPointSnapshot
     * @param  list<OrderLineDetailData>  $lines
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $status,
        public string $paymentStatus,
        public string $paymentMethodId,
        public string $currencyCode,
        public string $mode,
        public string $locale,
        public string $email,
        public string $buyerType,
        public ?string $companyName,
        public array $billingAddress,
        public ?array $shippingAddress,
        public string $totalNetAmount,
        public string $totalVatAmount,
        public string $totalGrossAmount,
        public ?string $deliveryName,
        public ?string $deliveryNetAmount,
        public ?string $deliveryVatAmount,
        public ?string $deliveryGrossAmount,
        public ?array $deliveryPickupPointSnapshot,
        public array $lines,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $createdAt,
        public ?PickupFulfillmentData $pickup = null,
    ) {}

    /** Whether the order is delivered to an address; pickup and digital orders have no delivery destination. */
    public function shipsToAddress(): bool
    {
        return $this->deliveryName !== null && $this->deliveryPickupPointSnapshot === null;
    }

    /**
     * Order-level pricing adjustments, summed from the per-line shares.
     *
     * @return list<array{code: string, label: string, gross_amount: string}>
     */
    public function adjustmentTotals(): array
    {
        $totals = [];
        foreach ($this->lines as $line) {
            foreach ($line->adjustmentAllocations as $share) {
                $totals[$share['code']] ??= ['code' => $share['code'], 'label' => $share['label'], 'gross_amount' => '0'];
                $totals[$share['code']]['gross_amount'] = bcadd($totals[$share['code']]['gross_amount'], $share['gross_amount'], 6);
            }
        }

        return array_values($totals);
    }
}
