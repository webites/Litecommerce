<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Coupons\Contracts\CouponPricer;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryOptionQuote;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Nothing here is ever persisted to the draft before confirm — a "review
 * this draft" request always recomputes live and compares hashes against
 * what the client already has, per the pipeline's hash-confirmation rule
 * in docs/knowledge-base/05-orders.md ("Front potwierdza konkretny hash").
 *
 * Adds pipeline steps 4-6 on top of PriceLines' steps 2-3: delivery
 * quoting, its free threshold (already computed against PriceLines'
 * post-coupon product totals — "próg z wartości... po rabatach",
 * 06-fulfillment.md), and finally spending each code's still-unused
 * balance against the delivery amount, same ratio-based net/VAT split
 * PriceLines uses for product lines.
 */
final class PriceCheckoutDraft
{
    public function __construct(
        private readonly PriceLines $priceLines,
        private readonly DeliveryMethodCatalog $delivery,
        private readonly CouponPricer $coupons,
        private readonly CurrencyRepository $currencies,
    ) {}

    public function price(CheckoutDraft $draft, string $locale): PricedCart
    {
        $items = CartItem::query()->where('cart_id', $draft->cart_id)->get();
        $priced = $this->priceLines->price($items, $draft->currency_code, $locale, $draft->cart_id);

        $hasPhysical = false;

        foreach ($priced->lines as $line) {
            if ($line->productType === ProductType::Physical->value) {
                $hasPhysical = true;

                break;
            }
        }

        if (! $hasPhysical) {
            return $priced;
        }

        $context = new DeliveryQuoteContext(
            currencyCode: $priced->currencyCode,
            countryCode: $this->deliveryCountryCode($draft),
            goodsAmountForThreshold: $priced->totalGrossAmount,
        );

        $options = $this->delivery->quoteAvailable($context, $locale);
        $quote = $draft->delivery_method_id === null ? null : $this->delivery->quote($draft->delivery_method_id, $context, $locale);

        $delivery = $quote === null
            ? new PricedDelivery($options, $draft->delivery_method_id, $draft->delivery_pickup_point_id, null)
            : $this->applyCouponsToDelivery($options, $draft, $quote, $priced->couponOutcomes, $priced->currencyCode);

        return new PricedCart(
            $priced->currencyCode, $priced->lines, $priced->totalNetAmount, $priced->totalVatAmount,
            $priced->totalGrossAmount, $priced->allAvailable, $delivery, $priced->couponOutcomes,
        );
    }

    /** @param list<DeliveryOptionQuote> $options */
    private function applyCouponsToDelivery(
        array $options,
        CheckoutDraft $draft,
        DeliveryOptionQuote $quote,
        array $couponOutcomes,
        string $currencyCode,
    ): PricedDelivery {
        $result = $this->coupons->allocateToDelivery($couponOutcomes, $quote->grossAmount, $currencyCode);

        if (! Decimal::isPositive($result->totalDiscount)) {
            return new PricedDelivery($options, $draft->delivery_method_id, $draft->delivery_pickup_point_id, $quote, '0', $quote->netAmount, $quote->vatAmount, $quote->grossAmount);
        }

        $currency = $this->currencies->find($currencyCode);
        $decimals = $currency === null ? 2 : $currency->decimalPlaces;
        $newGross = Decimal::roundHalfUp(Decimal::sub($quote->grossAmount, $result->totalDiscount), $decimals);
        $ratio = Decimal::isPositive($quote->grossAmount) ? Decimal::div($quote->vatAmount, $quote->grossAmount) : '0';
        $newVat = Decimal::roundHalfUp(Decimal::mul($newGross, $ratio), $decimals);
        $newNet = Decimal::roundHalfUp(Decimal::sub($newGross, $newVat), $decimals);

        return new PricedDelivery(
            $options, $draft->delivery_method_id, $draft->delivery_pickup_point_id, $quote,
            $result->totalDiscount, $newNet, $newVat, $newGross, $result->entries,
        );
    }

    public function hash(PricedCart $priced): string
    {
        $payload = [
            'currency' => $priced->currencyCode,
            'lines' => array_map(
                static fn (PricedLine $line): array => [
                    $line->variantId, $line->quantity, $line->unitAmount,
                    $line->lineNetAmount, $line->lineVatAmount, $line->lineGrossAmount,
                ],
                $priced->lines,
            ),
            'total_net' => $priced->totalNetAmount,
            'total_vat' => $priced->totalVatAmount,
            'total_gross' => $priced->totalGrossAmount,
            'delivery' => $priced->delivery?->quote === null ? null : [
                $priced->delivery->quote->methodId, $priced->delivery->selectedPickupPointId,
                $priced->delivery->finalNetAmount, $priced->delivery->finalVatAmount, $priced->delivery->finalGrossAmount,
            ],
            'coupons' => array_map(
                static fn ($o): array => [$o->code, $o->status->value, $o->allocatedAmount],
                $priced->couponOutcomes,
            ),
        ];

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** Address a physical shipment would go to — billing unless a different shipping address was requested. */
    private function deliveryCountryCode(CheckoutDraft $draft): ?string
    {
        $address = $draft->ships_to_different_address ? $draft->shipping_address : $draft->billing_address;

        return $address['country_code'] ?? null;
    }
}
