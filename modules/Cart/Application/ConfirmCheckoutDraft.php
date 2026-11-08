<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Delivery\Contracts\DeliveryPickupPointData;
use Litecommerce\Modules\Identity\Contracts\CustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Contracts\PurchaseBlockStatus;
use Litecommerce\Modules\Orders\Contracts\OrderPlacementLineData;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\PlaceOrderData;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;

/**
 * The "Zatwierdzenie" step of docs/knowledge-base/05-orders.md's pipeline
 * — including the atomic hand-off to Orders. The default MVP payment
 * method is bank transfer until storefront payment selection is presented
 * by the theme; a fully coupon-covered total requires the caller to
 * request `core.coupon_full_coverage` explicitly (no storefront exists yet
 * to auto-select it — the same integration gap as picking any other
 * payment method today).
 */
final class ConfirmCheckoutDraft
{
    public function __construct(
        private readonly PriceCheckoutDraft $pricer,
        private readonly CustomerPurchaseBlockChecker $blocks,
        private readonly OrderPlacer $orders,
        private readonly PaymentMethodRegistry $paymentMethods,
        private readonly MarketingConsentRegistry $consents,
        private readonly ProductTypeRegistry $productTypes,
    ) {}

    /** @param list<int> $ownedCartIds */
    public function execute(string $draftId, array $ownedCartIds, string $submittedHash, string $locale, string $paymentMethodId): ConfirmResult
    {
        // MySQL/MariaDB default to REPEATABLE READ, which takes gap/next-key locks even
        // for an equality+range SELECT ... FOR UPDATE that matches zero rows (see
        // ManageStockReservations::releaseExpiredForLockedItem()) — that gap lock can
        // extend into a neighbouring stock_item's index entries and deadlock two
        // transactions that never touch the same variant. READ COMMITTED takes only
        // record locks for that same statement, removing this deadlock source entirely
        // (MySQL docs, "InnoDB Locking" — READ COMMITTED locking reads/writes). Skipped on
        // SQLite (the test suite's driver): it has no such statement and no gap locks to
        // begin with, so there is nothing here to verify outside a real MySQL run — see
        // docs/knowledge-base/10-operations.md "Testy obciążeniowe".
        $isMysqlLike = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        if ($isMysqlLike) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        try {
            return $this->confirmWithRetry($draftId, $ownedCartIds, $submittedHash, $locale, $paymentMethodId);
        } finally {
            if ($isMysqlLike) {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
        }
    }

    /**
     * $attempts=3: PlaceOrder/ManageStockReservations open their own nested
     * DB::transaction() (savepoints) inside this one — on a real MySQL deadlock,
     * Laravel only retries at the OUTERMOST transaction (nested calls just rethrow
     * DeadlockException, see Illuminate\Database\Concerns\ManagesTransactions::
     * handleTransactionException()), so the retry has to live here, not in those
     * inner calls. The retried closure re-locks/re-prices/re-checks from scratch and
     * PlaceOrder::place() is idempotent on (source_type, source_id), so re-running it
     * after a rolled-back attempt is safe.
     *
     * @param  list<int>  $ownedCartIds
     */
    private function confirmWithRetry(string $draftId, array $ownedCartIds, string $submittedHash, string $locale, string $paymentMethodId): ConfirmResult
    {
        return DB::transaction(function () use ($draftId, $ownedCartIds, $submittedHash, $locale, $paymentMethodId): ConfirmResult {
            $draft = CheckoutDraft::query()->whereIn('cart_id', $ownedCartIds)->lockForUpdate()->findOrFail($draftId);

            if ($draft->status === 'confirmed') {
                return ConfirmResult::alreadyConfirmed($draft, $this->orders->findBySource('checkout_draft', $draft->id));
            }

            // Locks the cart against concurrent AddCartItem/UpdateCartItemQuantity/
            // RemoveCartItem for the rest of this transaction, so pricing and
            // "close the source cart" below observe the same, stable line items.
            $cartId = $draft->cart_id;
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);

            if ($cart->status === 'checked_out') {
                throw new CartValidationException('cart_checked_out', 'The cart has already been checked out.');
            }

            $priced = $this->pricer->price($draft, $locale);
            $hash = $this->pricer->hash($priced);

            $this->assertReady($draft, $priced);
            $this->assertNotBlocked($draft);

            if ($hash !== $submittedHash) {
                return ConfirmResult::hashMismatch($priced, $hash);
            }

            if (! $priced->allAvailable) {
                throw new CartValidationException('items_unavailable', 'Some cart items are unavailable.');
            }

            try {
                $paymentMethod = $this->paymentMethods->get($paymentMethodId);
            } catch (\InvalidArgumentException) {
                throw new CartValidationException('payment_method_unavailable', 'The selected payment method is unavailable.');
            }
            $containsDigital = collect($priced->lines)->contains(fn (PricedLine $line): bool => $this->productTypes->get($line->productType)->grantsDigitalAccess);
            $containsPhysical = collect($priced->lines)->contains(fn (PricedLine $line): bool => $this->productTypes->get($line->productType)->requiresDelivery);
            if (! $paymentMethod->available(new PaymentAvailabilityContext(
                $draft->id, $priced->grandTotalGrossAmount(), $priced->currencyCode,
                $containsPhysical, $containsDigital, $draft->customer_account_id,
                $priced->delivery?->selectedMethodId,
            ))) {
                throw new CartValidationException('payment_method_unavailable', 'The selected payment method is unavailable.');
            }

            $draft->update([
                'status' => 'confirmed',
                'price_snapshot' => $this->snapshot($priced),
                'price_hash' => $hash,
                'confirmed_at' => now(),
                'version' => $draft->version + 1,
            ]);

            // "Zatwierdzenie zamyka wersję źródła atomowo" — the cart that
            // fed this draft stops accepting mutations; the resolver will
            // hand out a fresh one for further shopping.
            Cart::query()->whereKey($cartId)->update([
                'status' => 'checked_out',
                'version' => DB::raw('version + 1'),
            ]);

            $pickupPoint = $this->selectedPickupPoint($priced);

            $placedOrder = $this->orders->place(new PlaceOrderData(
                sourceType: 'checkout_draft', sourceId: $draft->id,
                customerAccountId: $draft->customer_account_id, buyerType: $draft->buyer_type,
                email: (string) $draft->email, companyName: $draft->company_name,
                taxIdentifier: $draft->tax_identifier, registrationNumber: $draft->registration_number,
                phone: $draft->phone, customerNotes: $draft->notes,
                billingAddress: $draft->billing_address ?? [],
                shippingAddress: $draft->ships_to_different_address ? $draft->shipping_address : null,
                locale: $locale, currencyCode: $priced->currencyCode,
                paymentMethodId: $paymentMethod->descriptor()->id,
                paymentKind: $paymentMethod->descriptor()->kind,
                totalNetAmount: $priced->grandTotalNetAmount(), totalVatAmount: $priced->grandTotalVatAmount(),
                totalGrossAmount: $priced->grandTotalGrossAmount(),
                lines: array_map(static fn (PricedLine $line): OrderPlacementLineData => new OrderPlacementLineData(
                    variantId: $line->variantId, productType: (string) $line->productType,
                    sku: $line->sku, displayName: $line->displayName, quantity: $line->quantity,
                    unitAmount: (string) $line->unitAmount, lineNetAmount: (string) $line->lineNetAmount,
                    lineVatAmount: (string) $line->lineVatAmount, lineGrossAmount: (string) $line->lineGrossAmount,
                    couponAllocations: $line->couponAllocations, adjustmentAllocations: $line->adjustmentAllocations,
                ), $priced->lines),
                deliveryMethodId: $priced->delivery?->quote?->methodId,
                deliveryKind: $priced->delivery?->quote?->kind,
                deliveryName: $priced->delivery?->quote?->name,
                deliveryNetAmount: $priced->delivery?->finalNetAmount,
                deliveryVatAmount: $priced->delivery?->finalVatAmount,
                deliveryGrossAmount: $priced->delivery?->finalGrossAmount,
                deliveryPickupPointId: $pickupPoint?->id,
                deliveryPickupPointSnapshot: $this->pickupPointSnapshot($pickupPoint),
                couponUsages: $this->couponUsages($priced),
            ));

            if ($draft->marketing_consent) {
                $this->recordMarketingConsentAfterCommit((string) $draft->email, $locale);
            }

            return ConfirmResult::confirmed($draft->fresh(), $placedOrder);
        }, 3);
    }

    /** A failure to record the consent never fails the order; without a record there is simply no consent. */
    private function recordMarketingConsentAfterCommit(string $email, string $locale): void
    {
        DB::afterCommit(function () use ($email, $locale): void {
            try {
                $this->consents->grant($email, ConsentSource::Checkout, $locale);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    private function assertReady(CheckoutDraft $draft, PricedCart $priced): void
    {
        if ($draft->email === null || $draft->email === '') {
            throw new CartValidationException('email_required', 'An e-mail address is required.');
        }

        if ($draft->buyer_type === 'company' && ($draft->company_name === null || $draft->company_name === '')) {
            throw new CartValidationException('company_name_required', 'A company name is required for a business purchase.');
        }

        if (! $this->isCompleteAddress($draft->billing_address)) {
            throw new CartValidationException('billing_address_required', 'The billing address is incomplete.');
        }

        $hasPhysicalLines = false;

        foreach ($priced->lines as $line) {
            if ($this->productTypes->get($line->productType)->requiresDelivery) {
                $hasPhysicalLines = true;

                break;
            }
        }

        if ($draft->ships_to_different_address && $hasPhysicalLines && ! $this->isCompleteAddress($draft->shipping_address)) {
            throw new CartValidationException('shipping_address_required', 'The shipping address is incomplete.');
        }

        if ($priced->delivery === null) {
            return;
        }

        if ($draft->delivery_method_id === null) {
            throw new CartValidationException('delivery_method_required', 'A delivery method is required.');
        }

        if ($priced->delivery->quote === null) {
            throw new CartValidationException('delivery_method_unavailable', 'The selected delivery method is no longer available.');
        }

        if ($priced->delivery->quote->kind === 'pickup' && $this->selectedPickupPoint($priced) === null) {
            throw new CartValidationException('pickup_point_required', 'A pickup point is required.');
        }
    }

    private function selectedPickupPoint(PricedCart $priced): ?DeliveryPickupPointData
    {
        $quote = $priced->delivery?->quote;
        $pointId = $priced->delivery?->selectedPickupPointId;

        if ($quote === null || $pointId === null) {
            return null;
        }

        foreach ($quote->pickupPoints as $point) {
            if ($point->id === $pointId) {
                return $point;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function pickupPointSnapshot(?DeliveryPickupPointData $point): ?array
    {
        if ($point === null) {
            return null;
        }

        return [
            'id' => $point->id, 'name' => $point->name, 'country_code' => $point->countryCode,
            'postal_code' => $point->postalCode, 'city' => $point->city, 'region' => $point->region,
            'address_line_1' => $point->addressLine1, 'address_line_2' => $point->addressLine2,
            'instructions' => $point->instructions,
        ];
    }

    /** @param array<string, mixed>|null $address */
    private function isCompleteAddress(?array $address): bool
    {
        if ($address === null) {
            return false;
        }

        foreach (['recipient_name', 'country_code', 'postal_code', 'city', 'address_line_1'] as $field) {
            if (! isset($address[$field]) || $address[$field] === '') {
                return false;
            }
        }

        return true;
    }

    private function assertNotBlocked(CheckoutDraft $draft): void
    {
        $status = $draft->customer_account_id !== null
            ? $this->blocks->forCustomerAccount($draft->customer_account_id)
            : ($this->blocks->isGuestEmailBlocked((string) $draft->email) ? PurchaseBlockStatus::blocked(null) : PurchaseBlockStatus::allowed());

        if ($status->blocked) {
            throw new CartValidationException('purchase_blocked', $status->reason ?? 'Purchasing is currently unavailable for this account.', ['reason' => $status->reason]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(PricedCart $priced): array
    {
        return [
            'currency_code' => $priced->currencyCode,
            'lines' => array_map(static fn (PricedLine $line): array => [
                'variant_id' => $line->variantId,
                'sku' => $line->sku,
                'display_name' => $line->displayName,
                'product_type' => $line->productType,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unitAmount,
                'line_net_amount' => $line->lineNetAmount,
                'line_vat_amount' => $line->lineVatAmount,
                'line_gross_amount' => $line->lineGrossAmount,
                'coupon_discount_amount' => $line->couponDiscountAmount,
            ], $priced->lines),
            'total_net_amount' => $priced->totalNetAmount,
            'total_vat_amount' => $priced->totalVatAmount,
            'total_gross_amount' => $priced->totalGrossAmount,
            'coupons' => array_map(static fn ($o): array => [
                'code' => $o->code, 'status' => $o->status->value, 'allocated_amount' => $o->allocatedAmount,
            ], $priced->couponOutcomes),
            'delivery' => $priced->delivery?->quote === null ? null : [
                'method_id' => $priced->delivery->quote->methodId,
                'kind' => $priced->delivery->quote->kind,
                'name' => $priced->delivery->quote->name,
                'net_amount' => $priced->delivery->finalNetAmount,
                'vat_amount' => $priced->delivery->finalVatAmount,
                'gross_amount' => $priced->delivery->finalGrossAmount,
                'coupon_discount_amount' => $priced->delivery->couponDiscountAmount,
                'pickup_point' => $this->pickupPointSnapshot($this->selectedPickupPoint($priced)),
            ],
            'grand_total_net_amount' => $priced->grandTotalNetAmount(),
            'grand_total_vat_amount' => $priced->grandTotalVatAmount(),
            'grand_total_gross_amount' => $priced->grandTotalGrossAmount(),
        ];
    }

    /**
     * Aggregates each code's product (PriceLines) and delivery
     * (PriceCheckoutDraft) allocation into one order-level entry — the
     * shape PlaceOrder reserves against, per PlaceOrderData::$couponUsages.
     *
     * @return list<array{code:string,base_amount:string,product_gross_amount:string,delivery_gross_amount:string}>
     */
    private function couponUsages(PricedCart $priced): array
    {
        $usages = [];

        foreach ($priced->lines as $line) {
            foreach ($line->couponAllocations as $allocation) {
                $code = $allocation['code'];
                $usages[$code] ??= ['code' => $code, 'base_amount' => '0', 'product_gross_amount' => '0', 'delivery_gross_amount' => '0'];
                $usages[$code]['base_amount'] = bcadd($usages[$code]['base_amount'], $allocation['base_amount'], 6);
                $usages[$code]['product_gross_amount'] = bcadd($usages[$code]['product_gross_amount'], $allocation['amount'], 6);
            }
        }

        foreach ($priced->delivery === null ? [] : $priced->delivery->couponEntries as $entry) {
            $usages[$entry->code] ??= ['code' => $entry->code, 'base_amount' => '0', 'product_gross_amount' => '0', 'delivery_gross_amount' => '0'];
            $usages[$entry->code]['base_amount'] = bcadd($usages[$entry->code]['base_amount'], $entry->baseAmount, 6);
            $usages[$entry->code]['delivery_gross_amount'] = bcadd($usages[$entry->code]['delivery_gross_amount'], $entry->amount, 6);
        }

        return array_values($usages);
    }
}
