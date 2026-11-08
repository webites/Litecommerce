{{-- One hidden block per offer, keyed by its optionSignature via data-storefront-show-if; reacts to whatever variant-picker.blade.php's radios reveal. --}}
@foreach ($offers as $offer)
    <div @if ($offer->optionSignature !== '') data-storefront-show-if="{{ $offer->optionSignature }}" hidden @endif>
        <div class="price-block">
            @if ($offer->promotionalActive && $offer->promotionalUnitAmount !== null)
                <span class="price-regular">{{ formatMoneyAmount($offer->regularUnitAmount, $currencyCode, $currencyDecimalPlaces, $locale) }}</span>
                <span class="price-current price-promo">{{ formatMoneyAmount($offer->promotionalUnitAmount, $currencyCode, $currencyDecimalPlaces, $locale) }}</span>
            @elseif ($offer->unitAmount !== null)
                <span class="price-current">{{ formatMoneyAmount($offer->unitAmount, $currencyCode, $currencyDecimalPlaces, $locale) }}</span>
            @else
                <span class="muted">{{ trans('catalog::product.price_unavailable') }}</span>
            @endif

            @unless ($offer->available)
                <span class="badge badge-danger">{{ trans('catalog::product.unavailable') }}</span>
            @endunless
        </div>

        <form data-storefront="ajax" action="/cart/items" method="post"
              data-storefront-success-message="{{ trans('catalog::product.added_to_cart') }}" class="purchase-row">
            <input type="hidden" name="variant_id" value="{{ $offer->variantId }}">
            <div class="stepper" data-storefront-stepper>
                <button type="button" data-storefront-step="-1" @disabled(! $offer->available) aria-label="{{ trans('cart::cart.decrease_quantity') }}">–</button>
                <input type="text" inputmode="numeric" class="mono" name="quantity" value="1" readonly aria-label="{{ trans('cart::cart.quantity') }}">
                <button type="button" data-storefront-step="1" @disabled(! $offer->available) aria-label="{{ trans('cart::cart.increase_quantity') }}">+</button>
            </div>
            <button type="submit" class="btn btn-primary" @disabled(! $offer->available)>{{ trans('catalog::product.add_to_cart') }}</button>
            <p data-storefront-message role="status" class="feedback" style="width: 100%;"></p>
        </form>
    </div>
@endforeach
