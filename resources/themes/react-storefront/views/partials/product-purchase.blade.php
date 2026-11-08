{{--
    Single-quoted attributes: @json()'s output has literal double quotes and would
    break a double-quoted attribute. data-preselected/data-base-url let ProductPurchase.jsx
    preselect a variant deep-link's combination and keep the URL in sync as the
    shopper picks — see ProductPurchase.jsx's own comments for the client side.
--}}
@php
    $purchaseOffers = array_map(static fn ($offer): array => [
        'variantId' => $offer->variantId,
        'sku' => $offer->sku,
        'unitAmount' => $offer->unitAmount,
        'regularUnitAmount' => $offer->regularUnitAmount,
        'promotionalUnitAmount' => $offer->promotionalUnitAmount,
        'promotionalActive' => $offer->promotionalActive,
        'available' => $offer->available,
        'optionSignature' => $offer->optionSignature,
    ], $offers);
@endphp
<div
    id="product-purchase-root"
    data-axes='@json($axes)'
    data-offers='@json($purchaseOffers)'
    data-currency="{{ $currencyCode }}"
    data-currency-decimals="{{ $currencyDecimalPlaces }}"
    data-preselected='@json($preselectedOptionValues ?? new stdClass())'
    data-base-url="{{ route('storefront.catalog.show', ['locale' => $locale, 'slug' => $product->slug]) }}"
></div>
