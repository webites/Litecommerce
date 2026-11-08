@extends('theme::layout')

@section('title', $page->product->name)
{{--
    Canonical/robots for a variant deep-link depend on the product's
    variant_seo_mode (Catalog\Contracts\VariantSeoMode): canonical_to_product
    (default) always points at the base product URL, exactly as before;
    indexed_separately makes the variant page self-canonical; noindex adds a
    robots noindex meta ONLY on the deep-link, never on the base product page.
--}}
@php $isVariantDeepLink = $page->preselectedOptionValues !== null; @endphp
@section('canonical_url', $isVariantDeepLink && $page->product->variantSeoMode === 'indexed_separately'
    ? url()->current()
    : route('storefront.catalog.show', ['locale' => $locale, 'slug' => $page->product->slug]))
@if ($isVariantDeepLink && $page->product->variantSeoMode === 'noindex')
    @section('robots', 'noindex')
@endif

@section('content')
    @php $product = $page->product; @endphp

    <p class="crumb">
        <a href="{{ route('storefront.catalog.index', ['locale' => $locale]) }}">{{ trans('storefront::messages.nav_products') }}</a>
        @if (count($product->categories) > 0)
            / <a href="{{ route('storefront.cms.show', ['locale' => $locale, 'slug' => $product->categories[0]->slug]) }}">{{ $product->categories[0]->name }}</a>
        @endif
        / {{ $product->name }}
    </p>

    <div class="product-page">
        <div class="product-gallery">
            @if (count($product->imageUrls) > 0)
                <div class="gallery-main">
                    @php(showImage($product->imageUrls[0], $product->imageSrcsets[0] ?? null, '(min-width: 900px) 566px, 100vw', $product->imageWidths[0] ?? null, $product->imageHeights[0] ?? null, ['alt' => $product->name]))
                </div>
                @if (count($product->imageUrls) > 1)
                    <div class="gallery-thumbs">
                        @foreach ($product->imageUrls as $index => $url)
                            <div class="thumb @if ($index === 0) is-active @endif">
                                @php(showImage($url, $product->imageSrcsets[$index] ?? null, '68px', attributes: ['alt' => '']))
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="gallery-main">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                </div>
            @endif
        </div>

        <div class="product-info">
            <h1>{{ $product->name }}</h1>

            {{-- Renders product-meta/product-details/product-purchase per config('storefront.regions.catalog.show'), a store-level setting, not this theme. --}}
            @themeRegions('catalog.show', [
                'product' => $product,
                'offers' => $page->variantOffers,
                'axes' => $product->optionAxes,
                'currencyCode' => $page->currencyCode,
                'currencyDecimalPlaces' => $page->currencyDecimalPlaces,
                'locale' => $locale,
                'preselectedOptionValues' => $page->preselectedOptionValues,
                'preselectedOptionSignature' => $page->preselectedOptionSignature,
            ])
        </div>
    </div>

    {{-- A sibling region group rendered outside .product-page — the description/spec table read poorly squeezed next to the gallery. --}}
    @themeRegions('catalog.show.below', [
        'product' => $product,
        'offers' => $page->variantOffers,
        'axes' => $product->optionAxes,
        'locale' => $locale,
        'preselectedOptionSignature' => $page->preselectedOptionSignature,
    ])

    @include('theme::partials.related-products', ['heading' => trans('catalog::product.upsells_heading'), 'items' => $page->upsells])
    @include('theme::partials.related-products', ['heading' => trans('catalog::product.cross_sells_heading'), 'items' => $page->crossSells])
@endsection
