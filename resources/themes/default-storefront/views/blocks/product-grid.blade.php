@if (isset($catalogListing))
    @include('theme::partials.catalog-listing', [
        'page' => $catalogListing->page,
        'searchCriteria' => $catalogListing->criteria,
        'searchFilters' => $catalogListing->filters,
        'filterAction' => $catalogFilterAction,
        'clearUrl' => $catalogClearUrl,
        'includeCategoryQuery' => false,
        'emptyActionUrl' => $catalogClearUrl,
        'emptyActionLabel' => trans('search::messages.clear'),
    ])
@elseif (count($products) > 0)
    <section class="cms-product-grid">
        <div class="product-grid">
            @foreach ($products as $item)
                @include('theme::partials.product-card')
            @endforeach
        </div>
    </section>
@endif
