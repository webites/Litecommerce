@extends('theme::layout')

@section('title', trans('catalog::product.listing_title'))

@section('content')
    <div class="page-head">
        <h1>{{ trans('catalog::product.listing_title') }}</h1>
        <span class="muted">{{ trans_choice('search::messages.results', $page->total, ['count' => $page->total]) }}</span>
        @if ($categoryId !== null)
            {{-- ProductCatalog::paginatePublished() takes only a category ID, no translated name lookup exists yet — the chip just confirms a filter is active. --}}
            <div class="category-chip">
                {{ trans('catalog::product.category_filter_active') }}
                <a href="{{ route('storefront.catalog.index', ['locale' => $locale]) }}" aria-label="{{ trans('catalog::product.clear_category_filter') }}" title="{{ trans('catalog::product.clear_category_filter') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </a>
            </div>
        @endif
    </div>

    @include('theme::partials.catalog-listing', [
        'emptyActionUrl' => $categoryId === null ? null : route('storefront.catalog.index', ['locale' => $locale]),
    ])
@endsection
