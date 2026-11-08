<div class="catalog-layout">
    <aside class="catalog-sidebar">
        @include('theme::partials.catalog-filters')
    </aside>
    <div class="catalog-results">
        @if (count($page->items) === 0)
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4a2 2 0 0 0-1 1.73v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                <h2>{{ trans('search::messages.no_results_title') }}</h2>
                <p>{{ trans('search::messages.no_results_text') }}</p>
                @if (isset($emptyActionUrl))
                    <a class="btn btn-primary" href="{{ $emptyActionUrl }}">{{ $emptyActionLabel ?? trans('catalog::product.view_all_products') }}</a>
                @endif
            </div>
        @else
            <div class="product-grid">
                @foreach ($page->items as $item)
                    @include('theme::partials.product-card')
                @endforeach
            </div>
            @include('theme::partials.pagination', ['currentPage' => $page->page, 'lastPage' => (int) ceil($page->total / max(1, $page->perPage)), 'label' => trans('catalog::product.pagination_label')])
        @endif
    </div>
</div>
