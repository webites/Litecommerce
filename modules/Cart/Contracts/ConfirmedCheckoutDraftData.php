<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;

/**
 * A checkout draft that reached the "confirmed" terminal state — the
 * stable read contract for extensions and later manual-order flows. The
 * customer checkout currently hands its snapshot to Orders atomically from
 * ConfirmCheckoutDraft, before an external payment is initiated.
 */
final readonly class ConfirmedCheckoutDraftData
{
    /** @param list<CheckoutDraftLineData> $lines */
    public function __construct(
        public string $id,
        public string $currencyCode,
        public BuyerProfileType $buyerType,
        public ?int $customerAccountId,
        public string $email,
        public ?string $companyName,
        public ?string $taxIdentifier,
        public ?string $registrationNumber,
        public ?string $phone,
        public ?string $notes,
        public CheckoutAddressData $billingAddress,
        public ?CheckoutAddressData $shippingAddress,
        public array $lines,
        public string $totalNetAmount,
        public string $totalVatAmount,
        public string $totalGrossAmount,
        public \DateTimeImmutable $confirmedAt,
    ) {}
}
