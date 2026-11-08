{{--
    Shared container: picking a variant value changes what data-storefront-show-if
    reveals below, keyed by the matching optionSignature. data-storefront-variant-base-url
    is the product's base URL (no variant segments) — bindVariantUrlSync() in
    runtime.js appends the picked combination's canonical segments to it.
--}}
<div class="product-purchase" data-storefront-show-if-container
     data-storefront-variant-base-url="{{ route('storefront.catalog.show', ['locale' => $locale, 'slug' => $product->slug]) }}">
    @include('theme::partials.variant-picker', ['axes' => $axes, 'preselectedOptionValues' => $preselectedOptionValues ?? null])

    @foreach ($offers as $offer)
        <p class="sku-line" @if ($offer->optionSignature !== '') data-storefront-show-if="{{ $offer->optionSignature }}" hidden @endif>SKU: {{ $offer->sku }}</p>
    @endforeach

    @include('theme::partials.add-to-cart', ['offers' => $offers, 'currencyCode' => $currencyCode, 'currencyDecimalPlaces' => $currencyDecimalPlaces])

    @if (count($axes) > 0)
        <p class="variant-unavailable" data-storefront-show-if-fallback hidden>{{ trans('catalog::product.pick_available_combination') }}</p>
    @endif
</div>
