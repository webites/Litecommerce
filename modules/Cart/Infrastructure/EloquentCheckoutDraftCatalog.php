<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure;

use Litecommerce\Modules\Cart\Contracts\CheckoutAddressData;
use Litecommerce\Modules\Cart\Contracts\CheckoutDraftCatalog;
use Litecommerce\Modules\Cart\Contracts\CheckoutDraftLineData;
use Litecommerce\Modules\Cart\Contracts\ConfirmedCheckoutDraftData;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;

final class EloquentCheckoutDraftCatalog implements CheckoutDraftCatalog
{
    public function findConfirmed(string $draftId): ?ConfirmedCheckoutDraftData
    {
        $draft = CheckoutDraft::query()->where('status', 'confirmed')->find($draftId);

        return $draft === null ? null : $this->toData($draft);
    }

    /** @param list<int> $ownedCartIds */
    public function findConfirmedOwnedBy(string $draftId, array $ownedCartIds): ?ConfirmedCheckoutDraftData
    {
        $draft = CheckoutDraft::query()->where('status', 'confirmed')->whereIn('cart_id', $ownedCartIds)->find($draftId);

        return $draft === null ? null : $this->toData($draft);
    }

    private function toData(CheckoutDraft $draft): ConfirmedCheckoutDraftData
    {
        $snapshot = $draft->price_snapshot ?? [];

        $lines = array_map(static fn (array $line): CheckoutDraftLineData => new CheckoutDraftLineData(
            variantId: (int) $line['variant_id'],
            sku: (string) $line['sku'],
            displayName: (string) $line['display_name'],
            productType: (string) ($line['product_type'] ?? 'physical'),
            quantity: (string) $line['quantity'],
            unitAmount: (string) $line['unit_amount'],
            lineNetAmount: (string) $line['line_net_amount'],
            lineVatAmount: (string) $line['line_vat_amount'],
            lineGrossAmount: (string) $line['line_gross_amount'],
        ), $snapshot['lines'] ?? []);

        return new ConfirmedCheckoutDraftData(
            id: $draft->id,
            currencyCode: $draft->currency_code,
            buyerType: BuyerProfileType::from($draft->buyer_type),
            customerAccountId: $draft->customer_account_id,
            email: (string) $draft->email,
            companyName: $draft->company_name,
            taxIdentifier: $draft->tax_identifier,
            registrationNumber: $draft->registration_number,
            phone: $draft->phone,
            notes: $draft->notes,
            billingAddress: $this->address($draft->billing_address),
            shippingAddress: $draft->ships_to_different_address ? $this->address($draft->shipping_address) : null,
            lines: $lines,
            totalNetAmount: (string) ($snapshot['total_net_amount'] ?? '0'),
            totalVatAmount: (string) ($snapshot['total_vat_amount'] ?? '0'),
            totalGrossAmount: (string) ($snapshot['total_gross_amount'] ?? '0'),
            confirmedAt: \DateTimeImmutable::createFromInterface($draft->confirmed_at),
        );
    }

    /** @param array<string, mixed>|null $address */
    private function address(?array $address): CheckoutAddressData
    {
        $address ??= [];

        return new CheckoutAddressData(
            recipientName: (string) ($address['recipient_name'] ?? ''),
            companyName: $address['company_name'] ?? null,
            countryCode: (string) ($address['country_code'] ?? ''),
            postalCode: (string) ($address['postal_code'] ?? ''),
            city: (string) ($address['city'] ?? ''),
            region: $address['region'] ?? null,
            addressLine1: (string) ($address['address_line_1'] ?? ''),
            addressLine2: $address['address_line_2'] ?? null,
            phone: $address['phone'] ?? null,
        );
    }
}
