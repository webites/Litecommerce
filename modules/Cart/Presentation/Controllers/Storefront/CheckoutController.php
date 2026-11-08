<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\I18n\CountryDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Cart\Application\CartResolver;
use Litecommerce\Modules\Cart\Application\CartValidationException;
use Litecommerce\Modules\Cart\Application\ConfirmCheckoutDraft;
use Litecommerce\Modules\Cart\Application\CreateCheckoutDraft;
use Litecommerce\Modules\Cart\Application\PriceCheckoutDraft;
use Litecommerce\Modules\Cart\Application\PricedCart;
use Litecommerce\Modules\Cart\Application\UpdateCheckoutDraft;
use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Cart\Presentation\Concerns\RespondsWithCartRejection;
use Litecommerce\Modules\Cart\Presentation\Requests\ConfirmCheckoutDraftRequest;
use Litecommerce\Modules\Cart\Presentation\Requests\UpdateCheckoutDraftRequest;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryOptionQuote;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStarter;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;

/**
 * The "Checkout" side of the module — see CartController for the cart
 * itself. A draft is always scoped to the cart ids the requester's own
 * guest/account identity owns (never trusts the {draft} route id alone),
 * so a leaked/guessed draft UUID cannot be read or mutated from another
 * session. store() needs the request's currently *open* cart (a draft is
 * built from it); show()/update()/confirm() use every cart id the
 * identity has ever owned, including already-checked-out ones, so a
 * confirmed draft's own review/replay keeps working after its source
 * cart closed — resolveCartId() would otherwise hand back a brand new
 * cart at that point and this draft would look like it belongs to
 * someone else.
 */
final class CheckoutController extends Controller
{
    use RespondsWithCartRejection;

    public function store(Request $request, CartResolver $resolver, CreateCheckoutDraft $action): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        try {
            $draft = $action->execute($cartId, $resolver->authenticatedCustomerId());
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return response()->json($this->presentDraft($draft), 201);
    }

    public function show(Request $request, string $draft, CartResolver $resolver, PriceCheckoutDraft $pricer): JsonResponse
    {
        $ownedCartIds = $resolver->resolveOwningCartIds($request);
        $draftModel = CheckoutDraft::query()->whereIn('cart_id', $ownedCartIds)->findOrFail($draft);

        $priced = $pricer->price($draftModel, app()->getLocale());

        return response()->json([
            ...$this->presentDraft($draftModel),
            'price' => $this->presentPrice($priced, $pricer->hash($priced)),
        ]);
    }

    public function update(UpdateCheckoutDraftRequest $request, string $draft, CartResolver $resolver, UpdateCheckoutDraft $action): JsonResponse
    {
        $ownedCartIds = $resolver->resolveOwningCartIds($request);

        try {
            $draftModel = $action->execute($draft, $ownedCartIds, $request->validatedData());
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return response()->json($this->presentDraft($draftModel));
    }

    public function confirm(ConfirmCheckoutDraftRequest $request, string $draft, CartResolver $resolver, ConfirmCheckoutDraft $action, PaymentAttemptStarter $payments): JsonResponse
    {
        $ownedCartIds = $resolver->resolveOwningCartIds($request);

        try {
            $result = $action->execute(
                $draft, $ownedCartIds, (string) $request->string('price_hash'), app()->getLocale(),
                (string) $request->input('payment_method_id', config('orders.default_payment_method')),
            );
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        if ($result->outcome === 'hash_mismatch') {
            return response()->json([
                'code' => 'price_changed',
                'message' => trans('cart::errors.price_changed'),
                'price' => $this->presentPrice($result->priced, $result->currentHash),
            ], 409);
        }

        $payment = $result->order === null ? null : $payments->start($result->order->id, (string) $result->draft?->email);

        return response()->json([
            ...$this->presentDraft($result->draft),
            'order' => $result->order === null ? null : [
                'id' => $result->order->id, 'status' => $result->order->status,
                'payment_status' => $result->order->paymentStatus,
            ],
            'payment' => $payment === null ? null : [
                'attempt_id' => $payment->id, 'status' => $payment->status->value,
                'redirect_url' => $payment->redirectUrl,
            ],
        ]);
    }

    /**
     * A storefront theme has no other way to know which payment methods it
     * may even offer the customer to pick from for this draft's current
     * amount/currency/contents — every built-in method (and any plugin's)
     * gates itself on PaymentAvailabilityContext, so this simply reuses the
     * exact same check ConfirmCheckoutDraft performs right before placing
     * the order, instead of duplicating that eligibility logic here.
     */
    public function paymentMethods(Request $request, string $draft, CartResolver $resolver, PriceCheckoutDraft $pricer, PaymentMethodRegistry $registry, ProductTypeRegistry $productTypes): JsonResponse
    {
        $ownedCartIds = $resolver->resolveOwningCartIds($request);
        $draftModel = CheckoutDraft::query()->whereIn('cart_id', $ownedCartIds)->findOrFail($draft);

        $priced = $pricer->price($draftModel, app()->getLocale());

        $containsPhysical = collect($priced->lines)->contains(fn ($line): bool => $productTypes->get($line->productType)->requiresDelivery);
        $containsDigital = collect($priced->lines)->contains(fn ($line): bool => $productTypes->get($line->productType)->grantsDigitalAccess);

        $context = new PaymentAvailabilityContext(
            $draftModel->id, $priced->grandTotalGrossAmount(), $priced->currencyCode,
            $containsPhysical, $containsDigital, $draftModel->customer_account_id,
            $priced->delivery?->selectedMethodId,
        );

        $available = array_values(array_filter(
            $registry->all(),
            fn (PaymentMethodDescriptor $descriptor): bool => $registry->get($descriptor->id)->available($context),
        ));

        return response()->json([
            'methods' => array_map(static fn (PaymentMethodDescriptor $descriptor): array => [
                'id' => $descriptor->id,
                'name' => $descriptor->name,
                'kind' => $descriptor->kind,
            ], $available),
        ]);
    }

    /**
     * The country picker's own data source (label + flag + code), filtered
     * to what Delivery can actually quote today — see
     * DeliveryMethodCatalog::sellableCountryCodes() for why that is not
     * simply "not excluded". Fetched asynchronously by the storefront
     * widget rather than embedded in the checkout page itself.
     */
    public function countries(DeliveryMethodCatalog $delivery): JsonResponse
    {
        $sellable = array_flip($delivery->sellableCountryCodes());
        $countries = array_values(array_filter(
            (new CountryDirectory)->all(app()->getLocale()),
            static fn (array $country): bool => isset($sellable[$country['code']]),
        ));

        return response()->json(['countries' => $countries]);
    }

    /** @return array<string, mixed> */
    private function presentDraft(CheckoutDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'status' => $draft->status,
            'buyer_type' => $draft->buyer_type,
            'email' => $draft->email,
            'company_name' => $draft->company_name,
            'tax_identifier' => $draft->tax_identifier,
            'registration_number' => $draft->registration_number,
            'phone' => $draft->phone,
            'notes' => $draft->notes,
            'marketing_consent' => $draft->marketing_consent,
            'currency_code' => $draft->currency_code,
            'billing_address' => $draft->billing_address,
            'ships_to_different_address' => $draft->ships_to_different_address,
            'shipping_address' => $draft->shipping_address,
            'delivery_method_id' => $draft->delivery_method_id,
            'delivery_pickup_point_id' => $draft->delivery_pickup_point_id,
            'confirmed_at' => $draft->confirmed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentPrice(PricedCart $priced, string $hash): array
    {
        $decimals = $this->decimalsOf($priced->totalGrossAmount);
        $linesDiscount = Decimal::roundHalfUp(
            array_reduce($priced->lines, fn (string $sum, $line): string => Decimal::add($sum, $line->couponDiscountAmount), '0'),
            $decimals,
        );
        $deliveryDiscount = $priced->delivery === null ? '0' : $priced->delivery->couponDiscountAmount;

        return [
            'hash' => $hash,
            'currency_code' => $priced->currencyCode,
            'lines' => array_map(fn ($line): array => [
                'variant_id' => $line->variantId,
                'sku' => $line->sku,
                'display_name' => $line->displayName,
                'quantity' => $this->trimDecimal($line->quantity),
                'unit_amount' => $line->unitAmount,
                'line_net_amount' => $line->lineNetAmount,
                'line_vat_amount' => $line->lineVatAmount,
                'line_gross_amount' => $line->lineGrossAmount,
                'coupon_discount_amount' => $line->couponDiscountAmount,
                'available' => $line->available,
                'unavailable_reason' => $line->unavailableReason,
            ], $priced->lines),
            // Same reconstruction as CartController::present() (see there
            // for why) — subtotal is LINES only, pre-discount; delivery's
            // own pre-discount price is already available per-option as
            // delivery.selected.gross_amount, so it is not duplicated here.
            'subtotal_gross_amount' => Decimal::roundHalfUp(Decimal::add($priced->totalGrossAmount, $linesDiscount), $decimals),
            'discount_amount' => Decimal::roundHalfUp(Decimal::add($linesDiscount, $deliveryDiscount), $decimals),
            'total_net_amount' => $priced->totalNetAmount,
            'total_vat_amount' => $priced->totalVatAmount,
            'total_gross_amount' => $priced->totalGrossAmount,
            'all_available' => $priced->allAvailable,
            'adjustments' => $priced->adjustments,
            'coupons' => array_map(static fn ($o): array => [
                'code' => $o->code, 'status' => $o->status->value, 'allocated_amount' => $o->allocatedAmount,
            ], $priced->couponOutcomes),
            'delivery' => $priced->delivery === null ? null : [
                'required' => true,
                'selected_method_id' => $priced->delivery->selectedMethodId,
                'selected_pickup_point_id' => $priced->delivery->selectedPickupPointId,
                'selected' => $priced->delivery->quote === null ? null : $this->presentDeliveryOption($priced->delivery->quote),
                'coupon_discount_amount' => $priced->delivery->couponDiscountAmount,
                'final_net_amount' => $priced->delivery->finalNetAmount,
                'final_vat_amount' => $priced->delivery->finalVatAmount,
                'final_gross_amount' => $priced->delivery->finalGrossAmount,
                'options' => array_map($this->presentDeliveryOption(...), $priced->delivery->options),
            ],
            'grand_total_net_amount' => $priced->grandTotalNetAmount(),
            'grand_total_vat_amount' => $priced->grandTotalVatAmount(),
            'grand_total_gross_amount' => $priced->grandTotalGrossAmount(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentDeliveryOption(DeliveryOptionQuote $option): array
    {
        return [
            'method_id' => $option->methodId,
            'kind' => $option->kind,
            'name' => $option->name,
            'net_amount' => $option->netAmount,
            'vat_amount' => $option->vatAmount,
            'gross_amount' => $option->grossAmount,
            'free_threshold_applied' => $option->freeThresholdApplied,
            'supports_cash_on_delivery' => $option->supportsCashOnDelivery,
            'supports_pay_on_pickup' => $option->supportsPayOnPickup,
            'pickup_points' => array_map(static fn ($point): array => [
                'id' => $point->id, 'name' => $point->name, 'country_code' => $point->countryCode,
                'postal_code' => $point->postalCode, 'city' => $point->city, 'region' => $point->region,
                'address_line_1' => $point->addressLine1, 'address_line_2' => $point->addressLine2,
                'instructions' => $point->instructions,
            ], $option->pickupPoints),
        ];
    }

    /**
     * Decimal::add()/roundHalfUp() need an explicit scale — this reads it
     * off an amount the pricing pipeline already rounded correctly (same
     * idiom as PricedCart::sum()), rather than guessing a fixed 2.
     */
    private function decimalsOf(string $amount): int
    {
        return str_contains($amount, '.') ? strlen(substr($amount, strpos($amount, '.') + 1)) : 0;
    }

    /**
     * Line quantities carry the model's decimal:6 storage scale (e.g.
     * "1.000000") even for whole-unit products; trim it for display the
     * same way money amounts already are, without losing genuine
     * fractional quantities (e.g. "2.500000" -> "2.5").
     */
    private function trimDecimal(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
