<div class="product-card">
    <a class="product-card-link" href="{{ $item->url ?? route('storefront.catalog.show', ['locale' => $locale, 'slug' => $item->slug]) }}">
        <div class="product-photo">
            @if ($item->promotionalActive && $item->unitAmount !== null)
                <span class="badge badge-danger">{{ trans('catalog::product.promotion') }}</span>
            @elseif (! $item->available)
                <span class="badge badge-neutral">{{ trans('catalog::product.unavailable') }}</span>
            @endif
            @if ($item->imageUrl)
                @php(showImage($item->imageUrl, $item->imageSrcset, '(min-width: 700px) 25vw, 50vw', $item->imageWidth, $item->imageHeight, ['alt' => $item->name]))
            @else
                <div class="no-image-thumb">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    {{ trans('catalog::product.no_image') }}
                </div>
            @endif
        </div>
        <div class="product-body">
            <div class="product-name">{{ $item->name }}</div>
            <div class="product-price-row">
                @if ($item->promotionalActive && $item->unitAmount !== null)
                    <span class="price-regular mono">{{ formatMoneyAmount($item->regularUnitAmount, $item->currencyCode, $item->currencyDecimalPlaces, $locale) }}</span>
                    <span class="price-current price-promo mono">{{ formatMoneyAmount($item->unitAmount, $item->currencyCode, $item->currencyDecimalPlaces, $locale) }}</span>
                @elseif ($item->unitAmount !== null)
                    <span class="price-current mono">{{ formatMoneyAmount($item->unitAmount, $item->currencyCode, $item->currencyDecimalPlaces, $locale) }}</span>
                @else
                    <span class="muted">{{ trans('catalog::product.price_unavailable') }}</span>
                @endif
            </div>
        </div>
    </a>

    @if ($item->available && $item->defaultVariantId !== 0)
        <div class="product-body" style="padding-top: 0;">
            <form data-storefront="ajax" action="/cart/items" method="post"
                  data-storefront-success-message="{{ trans('catalog::product.added_to_cart') }}" class="product-add">
                <input type="hidden" name="variant_id" value="{{ $item->defaultVariantId }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-secondary btn-block">{{ trans('catalog::product.add_to_cart') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
    @endif
</div>
