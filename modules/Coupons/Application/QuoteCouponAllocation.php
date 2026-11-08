<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Application;

use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentStatus;
use Litecommerce\Modules\Coupons\Contracts\CouponCodeAllocationOutcome;
use Litecommerce\Modules\Coupons\Contracts\CouponDeliveryAllocationEntry;
use Litecommerce\Modules\Coupons\Contracts\CouponDeliveryAllocationResult;
use Litecommerce\Modules\Coupons\Contracts\CouponPricer;
use Litecommerce\Modules\Coupons\Contracts\CouponProductAllocationResult;
use Litecommerce\Modules\Coupons\Domain\Decimal;
use Litecommerce\Modules\Coupons\Domain\ProportionalAllocator;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponSettings;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Read-only pipeline steps 3 and 6 — see Contracts\CouponPricer. Codes are
 * spent in a strict waterfall, in the given (attachment) order: a code is
 * allocated as much as it can give (up to its own balance) before the next
 * code gets a turn, both across product lines ("w kolejności zastosowania
 * zapisanej w koszyku" — 04-pricing-coupons.md #4) and again against the
 * delivery remainder (#5-#6) — never split proportionally between codes,
 * only between lines within one code's turn.
 */
final class QuoteCouponAllocation implements CouponPricer
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly ExchangeRateCatalog $exchangeRates,
    ) {}

    public function allocateToProducts(array $codes, string $currencyCode, ?int $customerAccountId, array $lines): CouponProductAllocationResult
    {
        $lineAllocations = [];
        $remaining = [];
        $promotional = [];

        foreach ($lines as $line) {
            $lineAllocations[$line->key] = [];
            $remaining[$line->key] = $line->grossAmount;
            $promotional[$line->key] = $line->promotional;
        }

        if (! CouponSettings::current()->enabled) {
            $outcomes = array_map(
                static fn (string $code): CouponCodeAllocationOutcome => new CouponCodeAllocationOutcome(
                    $code, CouponAttachmentStatus::ModuleDisabled, '0', '0', '0', '0', false,
                ),
                array_values(array_unique($codes)),
            );

            return new CouponProductAllocationResult($outcomes, $lineAllocations, '0');
        }

        $decimals = $this->currencyDecimals($currencyCode);
        $base = $this->currencies->base();
        $isBaseCurrency = $base !== null && $currencyCode === $base->code;
        $rate = $isBaseCurrency ? null : $this->exchangeRates->find($currencyCode)?->baseUnitsPerUnit;
        $currencyUnavailable = ! $isBaseCurrency && $rate === null;

        $outcomes = [];
        $totalDiscount = '0';
        $seen = [];

        foreach ($codes as $code) {
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $coupon = Coupon::query()->where('code', $code)->first();
            $status = $this->basicStatus($coupon, $customerAccountId);

            if ($status === CouponAttachmentStatus::Applied && $currencyUnavailable) {
                $status = CouponAttachmentStatus::CurrencyUnavailable;
            }

            if ($status !== CouponAttachmentStatus::Applied) {
                $outcomes[] = new CouponCodeAllocationOutcome($code, $status, '0', '0', '0', '0', $coupon !== null && $coupon->applies_to_delivery);

                continue;
            }

            $availableBase = $coupon->availableBalance();
            $availableInCurrency = $rate === null
                ? $availableBase
                : Decimal::roundHalfUp(Decimal::div($availableBase, $rate), $decimals);

            if (! Decimal::isPositive($availableInCurrency)) {
                $outcomes[] = new CouponCodeAllocationOutcome($code, CouponAttachmentStatus::Applied, '0', '0', '0', '0', $coupon->applies_to_delivery);

                continue;
            }

            $weights = [];
            foreach ($lines as $line) {
                $key = $line->key;
                if (Decimal::isPositive($remaining[$key]) && (! $promotional[$key] || $coupon->combinable_with_promotional)) {
                    $weights[$key] = $remaining[$key];
                }
            }

            $allocatedAmount = '0';

            if ($weights !== []) {
                $eligibleSum = array_reduce($weights, static fn (string $carry, string $w): string => Decimal::add($carry, $w), '0');
                $toAllocate = Decimal::min($availableInCurrency, $eligibleSum);
                $shares = ProportionalAllocator::allocate($toAllocate, $weights, $decimals);

                foreach ($shares as $key => $amount) {
                    if (! Decimal::isPositive($amount)) {
                        continue;
                    }
                    $baseShare = $rate === null ? $amount : Decimal::roundHalfUp(Decimal::mul($amount, $rate), 6);
                    $lineAllocations[$key][] = ['code' => $code, 'amount' => $amount, 'base_amount' => $baseShare];
                    $remaining[$key] = Decimal::sub($remaining[$key], $amount);
                    $allocatedAmount = Decimal::add($allocatedAmount, $amount);
                }
            }

            $allocatedAmount = Decimal::roundHalfUp($allocatedAmount, $decimals);
            $allocatedBaseAmount = $rate === null
                ? $allocatedAmount
                : Decimal::roundHalfUp(Decimal::mul($allocatedAmount, $rate), 6);
            $remainingForDelivery = Decimal::roundHalfUp(Decimal::sub($availableInCurrency, $allocatedAmount), $decimals);
            $remainingForDeliveryBase = Decimal::roundHalfUp(Decimal::sub($availableBase, $allocatedBaseAmount), 6);

            $outcomes[] = new CouponCodeAllocationOutcome(
                $code, CouponAttachmentStatus::Applied, $allocatedAmount, $allocatedBaseAmount,
                $remainingForDelivery, $remainingForDeliveryBase, $coupon->applies_to_delivery,
            );
            $totalDiscount = Decimal::add($totalDiscount, $allocatedAmount);
        }

        return new CouponProductAllocationResult($outcomes, $lineAllocations, Decimal::roundHalfUp($totalDiscount, $decimals));
    }

    public function allocateToDelivery(array $productOutcomes, string $deliveryGrossAmount, string $currencyCode): CouponDeliveryAllocationResult
    {
        $decimals = $this->currencyDecimals($currencyCode);
        $remainingDelivery = $deliveryGrossAmount;
        $entries = [];
        $total = '0';

        foreach ($productOutcomes as $outcome) {
            if (! $outcome->allowsDelivery || ! Decimal::isPositive($outcome->remainingForDelivery) || ! Decimal::isPositive($remainingDelivery)) {
                continue;
            }

            $amount = Decimal::roundHalfUp(Decimal::min($outcome->remainingForDelivery, $remainingDelivery), $decimals);

            if (! Decimal::isPositive($amount)) {
                continue;
            }

            $ratio = Decimal::div($amount, $outcome->remainingForDelivery);
            $baseAmount = Decimal::roundHalfUp(Decimal::mul($outcome->remainingForDeliveryBaseAmount, $ratio), 6);

            $entries[] = new CouponDeliveryAllocationEntry($outcome->code, $amount, $baseAmount);
            $remainingDelivery = Decimal::sub($remainingDelivery, $amount);
            $total = Decimal::add($total, $amount);
        }

        return new CouponDeliveryAllocationResult($entries, Decimal::roundHalfUp($total, $decimals));
    }

    private function basicStatus(?Coupon $coupon, ?int $customerAccountId): CouponAttachmentStatus
    {
        if ($coupon === null) {
            return CouponAttachmentStatus::InvalidCode;
        }
        if ($coupon->status === 'blocked') {
            return CouponAttachmentStatus::Blocked;
        }
        if ($coupon->isExpired()) {
            return CouponAttachmentStatus::Expired;
        }
        if ($coupon->customer_account_id !== null && $coupon->customer_account_id !== $customerAccountId) {
            return $customerAccountId === null ? CouponAttachmentStatus::AccountRequired : CouponAttachmentStatus::AccountMismatch;
        }

        return CouponAttachmentStatus::Applied;
    }

    private function currencyDecimals(string $currencyCode): int
    {
        $currency = $this->currencies->find($currencyCode);

        return $currency === null ? 2 : $currency->decimalPlaces;
    }
}
