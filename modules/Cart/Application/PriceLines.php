<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Collection;
use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Cart\Domain\Quantity;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartCoupon;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Coupons\Contracts\CouponCodeAllocationOutcome;
use Litecommerce\Modules\Coupons\Contracts\CouponLineContext;
use Litecommerce\Modules\Coupons\Contracts\CouponPricer;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;

/**
 * Shared by the cart view and checkout draft pricing: implements pipeline
 * steps 2-3 of docs/knowledge-base/05-orders.md's "Pipeline wyceny" —
 * product pricing, VAT, coupon allocation to eligible lines and stock
 * availability. Never caches a price; always re-quotes from current
 * Catalog/Pricing/Warehouse/Coupons state.
 *
 * A coupon discount is applied by shrinking a line's gross amount and
 * re-splitting net/VAT from it using that SAME line's already-computed
 * vat/gross ratio — algebraically identical to re-deriving from its tax
 * rate (net-sourced or gross-sourced alike collapse to the same ratio),
 * so this needs no tax-rate details from Pricing at all. Verified against
 * the two-line worked example in docs/knowledge-base/04-pricing-coupons.md.
 */
final class PriceLines
{
    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly PriceQuoter $prices,
        private readonly StockLevelCatalog $stock,
        private readonly CurrencyRepository $currencies,
        private readonly CouponPricer $coupons,
        private readonly ProductTypeRegistry $productTypes,
    ) {}

    /** @param iterable<CartItem> $items */
    public function price(iterable $items, string $currencyCode, string $locale, int $cartId): PricedCart
    {
        $items = Collection::make($items)->values();
        $currency = $this->currencies->find($currencyCode);
        $decimals = $currency === null ? 2 : $currency->decimalPlaces;

        if ($items->isEmpty()) {
            [, $couponOutcomes] = $this->applyCoupons([], $currencyCode, $cartId, $decimals);

            return new PricedCart($currencyCode, [], '0', '0', '0', true, couponOutcomes: $couponOutcomes);
        }

        $variantIds = $items->map(fn (CartItem $item): int => (int) $item->variant_id)->unique()->values()->all();
        $variantData = $this->variants->findMany($variantIds, $locale);
        $stockData = $this->stock->findMany($variantIds);

        $quantities = [];
        foreach ($items as $item) {
            $variant = $variantData[(int) $item->variant_id] ?? null;
            if ($variant !== null && $variant->enabled) {
                $quantities[(int) $item->variant_id] = (string) $item->quantity;
            }
        }
        $quotes = $this->prices->quoteLines($quantities, $currencyCode);

        $lines = [];
        $allAvailable = true;

        foreach ($items as $item) {
            $variantId = (int) $item->variant_id;
            $quantity = $item->quantity;
            $variant = $variantData[$variantId] ?? null;

            if ($variant === null || ! $variant->enabled) {
                $allAvailable = false;
                $displayName = $variant === null ? "Produkt #{$variantId}" : $variant->displayName;
                $productType = $variant === null ? null : $variant->productType;
                $lines[] = new PricedLine($variantId, $variant?->productId, '', $displayName, $productType, $quantity, null, null, null, null, false, 'unavailable');

                continue;
            }

            $quote = $quotes[$variantId] ?? null;

            if ($quote === null) {
                $allAvailable = false;
                $lines[] = new PricedLine($variantId, $variant->productId, $variant->sku, $variant->displayName, $variant->productType, $quantity, null, null, null, null, false, 'no_price');

                continue;
            }

            $stockOk = true;

            if ($this->productTypes->get($variant->productType)->tracksStock) {
                $stockItem = $stockData[$variantId] ?? null;
                $available = $stockItem === null ? '0' : $stockItem->availableQuantity;
                $allowBackorder = $stockItem !== null && $stockItem->allowBackorder;

                if (! $allowBackorder && Quantity::compare($quantity, $available) > 0) {
                    $stockOk = false;
                    $allAvailable = false;
                }
            }

            $lines[] = new PricedLine(
                $variantId,
                $variant->productId,
                $variant->sku,
                $variant->displayName,
                $variant->productType,
                $quantity,
                $quote->unitEffectiveAmount,
                $quote->lineNetAmount,
                $quote->lineVatAmount,
                $quote->lineGrossAmount,
                $stockOk,
                $stockOk ? null : 'insufficient_stock',
                promotionalActive: $quote->promotionalActive,
            );
        }

        [$lines, $couponOutcomes] = $this->applyCoupons($lines, $currencyCode, $cartId, $decimals);

        $totalNet = '0';
        $totalVat = '0';
        $totalGross = '0';

        foreach ($lines as $line) {
            if ($line->lineGrossAmount === null) {
                continue;
            }
            $totalNet = Decimal::add($totalNet, $line->lineNetAmount);
            $totalVat = Decimal::add($totalVat, $line->lineVatAmount);
            $totalGross = Decimal::add($totalGross, $line->lineGrossAmount);
        }

        // Each line was already rounded to the currency's decimal places;
        // this only trims the internal summation scale back down to that
        // same precision, it never re-rounds a money amount.
        $totalNet = Decimal::roundHalfUp($totalNet, $decimals);
        $totalVat = Decimal::roundHalfUp($totalVat, $decimals);
        $totalGross = Decimal::roundHalfUp($totalGross, $decimals);

        return new PricedCart($currencyCode, $lines, $totalNet, $totalVat, $totalGross, $allAvailable, couponOutcomes: $couponOutcomes);
    }

    /**
     * @param  list<PricedLine>  $lines
     * @return array{0: list<PricedLine>, 1: list<CouponCodeAllocationOutcome>}
     */
    private function applyCoupons(array $lines, string $currencyCode, int $cartId, int $decimals): array
    {
        $codes = CartCoupon::query()->where('cart_id', $cartId)->orderBy('attached_at')->orderBy('id')->pluck('code')->all();

        if ($codes === []) {
            return [$lines, []];
        }

        $priceable = array_values(array_filter($lines, static fn (PricedLine $line): bool => $line->lineGrossAmount !== null));

        // Ascending variant id is the stable tie-break the largest-remainder
        // method needs ("stabilny identyfikator pozycji", #6) — independent
        // of the cart_items row order the lines were built in. Coupons is
        // asked even with zero priceable lines (an empty/all-unavailable
        // cart) so each code's actual status (invalid/blocked/expired/...)
        // is still reported, not silently assumed "Applied".
        usort($priceable, static fn (PricedLine $a, PricedLine $b): int => $a->variantId <=> $b->variantId);

        $customerAccountId = Cart::query()->whereKey($cartId)->value('customer_account_id');

        $contexts = array_map(
            static fn (PricedLine $line): CouponLineContext => new CouponLineContext($line->variantId, (string) $line->lineGrossAmount, $line->promotionalActive),
            $priceable,
        );

        $result = $this->coupons->allocateToProducts($codes, $currencyCode, $customerAccountId === null ? null : (int) $customerAccountId, $contexts);

        $adjusted = [];
        foreach ($lines as $line) {
            $allocations = $result->lineAllocations[$line->variantId] ?? [];
            $rawDiscount = array_reduce($allocations, static fn (string $carry, array $a): string => Decimal::add($carry, $a['amount']), '0');
            $discount = Decimal::roundHalfUp($rawDiscount, $decimals);

            if ($line->lineGrossAmount === null || ! Decimal::isPositive($discount)) {
                $adjusted[] = $line;

                continue;
            }

            $newGross = Decimal::roundHalfUp(Decimal::sub((string) $line->lineGrossAmount, $discount), $decimals);
            $ratio = Decimal::isPositive((string) $line->lineGrossAmount) ? Decimal::div((string) $line->lineVatAmount, (string) $line->lineGrossAmount) : '0';
            $newVat = Decimal::roundHalfUp(Decimal::mul($newGross, $ratio), $decimals);
            $newNet = Decimal::roundHalfUp(Decimal::sub($newGross, $newVat), $decimals);

            $adjusted[] = new PricedLine(
                $line->variantId, $line->productId, $line->sku, $line->displayName, $line->productType, $line->quantity,
                $line->unitAmount, $newNet, $newVat, $newGross, $line->available, $line->unavailableReason,
                $line->promotionalActive, $discount, $allocations,
            );
        }

        return [$adjusted, $result->codes];
    }
}
