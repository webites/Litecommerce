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
    ) {}

    /**
     * Products plus the currently selected, still-available delivery quote
     * — the amount actually charged. Equal to the product totals when no
     * delivery applies or none is chosen/available yet.
     */
    public function grandTotalNetAmount(): string
    {
        return $this->delivery?->finalNetAmount === null ? $this->totalNetAmount : $this->sum($this->totalNetAmount, $this->delivery->finalNetAmount);
    }

    public function grandTotalVatAmount(): string
    {
        return $this->delivery?->finalVatAmount === null ? $this->totalVatAmount : $this->sum($this->totalVatAmount, $this->delivery->finalVatAmount);
    }

    public function grandTotalGrossAmount(): string
    {
        return $this->delivery?->finalGrossAmount === null ? $this->totalGrossAmount : $this->sum($this->totalGrossAmount, $this->delivery->finalGrossAmount);
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
