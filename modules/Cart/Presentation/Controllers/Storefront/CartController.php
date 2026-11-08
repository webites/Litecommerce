<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Cart\Application\AddCartItem;
use Litecommerce\Modules\Cart\Application\AttachCouponCode;
use Litecommerce\Modules\Cart\Application\CartResolver;
use Litecommerce\Modules\Cart\Application\CartValidationException;
use Litecommerce\Modules\Cart\Application\DetachCouponCode;
use Litecommerce\Modules\Cart\Application\PricedLine;
use Litecommerce\Modules\Cart\Application\PriceLines;
use Litecommerce\Modules\Cart\Application\RemoveCartItem;
use Litecommerce\Modules\Cart\Application\SetCartCurrency;
use Litecommerce\Modules\Cart\Application\UpdateCartItemQuantity;
use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;
use Litecommerce\Modules\Cart\Presentation\Concerns\RespondsWithCartRejection;
use Litecommerce\Modules\Cart\Presentation\Requests\AddCartItemRequest;
use Litecommerce\Modules\Cart\Presentation\Requests\AttachCouponCodeRequest;
use Litecommerce\Modules\Cart\Presentation\Requests\SetCartCurrencyRequest;
use Litecommerce\Modules\Cart\Presentation\Requests\UpdateCartItemRequest;
use Litecommerce\Modules\Coupons\Contracts\CouponCodeAllocationOutcome;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;

/**
 * JSON API for the current request's cart — no storefront theme exists
 * yet (P1-013), so this is the presentation layer a future theme calls
 * into rather than server-rendered pages. Every mutating action re-shows
 * the fresh, live-priced cart so a caller never needs a second request.
 */
final class CartController extends Controller
{
    use RespondsWithCartRejection;

    public function show(Request $request, CartResolver $resolver, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        return $this->present($cartId, $priceLines, $media);
    }

    public function addItem(AddCartItemRequest $request, CartResolver $resolver, AddCartItem $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        try {
            $action->execute($cartId, (int) $request->integer('variant_id'), (string) $request->string('quantity'), app()->getLocale());
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return $this->present($cartId, $priceLines, $media);
    }

    public function updateItem(UpdateCartItemRequest $request, int $variantId, CartResolver $resolver, UpdateCartItemQuantity $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        try {
            $action->execute($cartId, $variantId, (string) $request->string('quantity'), app()->getLocale());
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return $this->present($cartId, $priceLines, $media);
    }

    public function removeItem(Request $request, int $variantId, CartResolver $resolver, RemoveCartItem $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);
        $action->execute($cartId, $variantId);

        return $this->present($cartId, $priceLines, $media);
    }

    public function setCurrency(SetCartCurrencyRequest $request, CartResolver $resolver, SetCartCurrency $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        try {
            $action->execute($cartId, strtoupper((string) $request->string('currency_code')));
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return $this->present($cartId, $priceLines, $media);
    }

    public function attachCoupon(AttachCouponCodeRequest $request, CartResolver $resolver, AttachCouponCode $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);

        try {
            $action->execute($cartId, (string) $request->string('code'), $request->string('pin')->value() ?: null, $resolver->authenticatedCustomerId());
        } catch (CartValidationException $exception) {
            return $this->rejected($exception);
        }

        return $this->present($cartId, $priceLines, $media);
    }

    public function detachCoupon(Request $request, string $code, CartResolver $resolver, DetachCouponCode $action, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cartId = $resolver->resolveCartId($request);
        $action->execute($cartId, $code);

        return $this->present($cartId, $priceLines, $media);
    }

    private function present(int $cartId, PriceLines $priceLines, MediaAttachments $media): JsonResponse
    {
        $cart = Cart::query()->findOrFail($cartId);
        $items = CartItem::query()->where('cart_id', $cartId)->get();
        $priced = $priceLines->price($items, $cart->currency_code, app()->getLocale(), $cartId);
        $imagesByProductId = $this->imagesByProductId($priced->lines, $media);

        $decimals = $this->decimalsOf($priced->totalGrossAmount);
        $discount = Decimal::roundHalfUp(
            array_reduce($priced->lines, fn (string $sum, $line): string => Decimal::add($sum, $line->couponDiscountAmount), '0'),
            $decimals,
        );

        return response()->json([
            'currency_code' => $priced->currencyCode,
            'lines' => array_map(fn ($line): array => [
                'variant_id' => $line->variantId,
                'sku' => $line->sku,
                'display_name' => $line->displayName,
                'image_url' => $line->productId === null ? null : ($imagesByProductId[$line->productId] ?? null),
                'quantity' => $this->trimDecimal($line->quantity),
                'unit_amount' => $line->unitAmount,
                'line_net_amount' => $line->lineNetAmount,
                'line_vat_amount' => $line->lineVatAmount,
                'line_gross_amount' => $line->lineGrossAmount,
                'coupon_discount_amount' => $line->couponDiscountAmount,
                'available' => $line->available,
                'unavailable_reason' => $line->unavailableReason,
            ], $priced->lines),
            // Lines already carry the coupon-shrunk gross amount (see
            // PriceLines::applyCoupons) — subtotal reconstructs the
            // pre-discount figure so the storefront can show "suma
            // częściowa" / "rabat" / "razem" as three separate, additive
            // lines instead of just the already-net total.
            'subtotal_gross_amount' => Decimal::roundHalfUp(Decimal::add($priced->totalGrossAmount, $discount), $decimals),
            'discount_amount' => $discount,
            'total_net_amount' => $priced->totalNetAmount,
            'total_vat_amount' => $priced->totalVatAmount,
            'total_gross_amount' => $priced->totalGrossAmount,
            'all_available' => $priced->allAvailable,
            'coupons' => array_map($this->presentCoupon(...), $priced->couponOutcomes),
        ]);
    }

    /**
     * @param  list<PricedLine>  $lines
     * @return array<int, ?string>
     */
    private function imagesByProductId(array $lines, MediaAttachments $media): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(fn ($line): ?int => $line->productId, $lines))));
        $galleries = $media->listMany('product', $productIds, 'gallery');

        $images = [];
        foreach ($productIds as $productId) {
            $images[$productId] = ($galleries[$productId][0] ?? null)?->url;
        }

        return $images;
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

    /** @return array<string, mixed> */
    private function presentCoupon(CouponCodeAllocationOutcome $outcome): array
    {
        return [
            'code' => $outcome->code,
            'status' => $outcome->status->value,
            'allocated_amount' => $outcome->allocatedAmount,
        ];
    }
}
