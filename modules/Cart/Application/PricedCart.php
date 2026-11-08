<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Coupons\Contracts\CouponCodeAllocationOutcome;

final readonly class PricedCart
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  list<CouponCodeAllocationOutcome>  $couponOutcomes  Every currently attached
     *                                                             code's status and product-side allocation (step 3) — step 6's
     *                                                             delivery share is layered on top by PriceCheckoutDraft, since
     *                                                             plain cart pricing has no delivery amount to spend it against.
     * @param  list<array{code:string,label:string,net_amount:string,vat_amount:string,gross_amount:string}>  $adjustments  Order-level
     *                                                                                                                      extra charges and discounts from PricingAdjustment plugins, applied after coupons.
     */
    public function __construct(
        public string $currencyCode,
        public array $lines,
        public string $totalNetAmount,
        public string $totalVatAmount,
        public string $totalGrossAmount,
        public bool $allAvailable,
        public ?PricedDelivery $delivery = null,
        public array $couponOutcomes = [],
        public array $adjustments = [],
    ) {}

    /**
     * Products plus the currently selected, still-available delivery quote
     * — the amount actually charged. Equal to the product totals when no
     * delivery applies or none is chosen/available yet.
     */
    public function grandTotalNetAmount(): string
    {
        $products = $this->delivery?->finalNetAmount === null ? $this->totalNetAmount : $this->sum($this->totalNetAmount, $this->delivery->finalNetAmount);

        return $this->sum($products, $this->adjustmentsTotal('net_amount'));
    }

    public function grandTotalVatAmount(): string
    {
        $products = $this->delivery?->finalVatAmount === null ? $this->totalVatAmount : $this->sum($this->totalVatAmount, $this->delivery->finalVatAmount);

        return $this->sum($products, $this->adjustmentsTotal('vat_amount'));
    }

    public function grandTotalGrossAmount(): string
    {
        $products = $this->delivery?->finalGrossAmount === null ? $this->totalGrossAmount : $this->sum($this->totalGrossAmount, $this->delivery->finalGrossAmount);

        return $this->sum($products, $this->adjustmentsTotal('gross_amount'));
    }

    private function adjustmentsTotal(string $field): string
    {
        return array_reduce($this->adjustments, static fn (string $sum, array $adjustment): string => Decimal::add($sum, $adjustment[$field]), '0');
    }

    /**
     * Both operands are already rounded to the currency's decimal places by
     * their own producers (PriceLines/DeliveryMethodCatalog) — this only
     * trims Decimal::add()'s wider internal scale back down to that same
     * precision, it never re-rounds a money amount.
     */
    private function sum(string $a, string $b): string
    {
        $decimals = str_contains($a, '.') ? strlen(substr($a, strpos($a, '.') + 1)) : 0;

        return Decimal::roundHalfUp(Decimal::add($a, $b), $decimals);
    }
}
