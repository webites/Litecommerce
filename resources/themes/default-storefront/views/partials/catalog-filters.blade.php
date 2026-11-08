@php
    $filterAction ??= route('storefront.catalog.index', ['locale' => $locale]);
    $clearUrl ??= route('storefront.catalog.index', array_filter(['locale' => $locale, 'category' => $categoryId]));
    $includeCategoryQuery ??= true;
@endphp
<form class="catalog-filter-block" method="get" action="{{ $filterAction }}">
    @if ($includeCategoryQuery && $categoryId !== null)<input type="hidden" name="category" value="{{ $categoryId }}">@endif

    <div class="catalog-filter-header">
        <h2>{{ trans('search::messages.filters') }}</h2>
    </div>
    <div class="catalog-filter-controls">
        <label class="field">
            <span>{{ trans('search::messages.search') }}</span>
            <input type="search" name="q" value="{{ $searchCriteria->query }}" maxlength="100" placeholder="{{ trans('search::messages.search_placeholder') }}">
        </label>
        <div class="catalog-filter-grid">
            <label class="field"><span>{{ trans('search::messages.price_from') }}</span><input name="min_price" inputmode="decimal" value="{{ $searchCriteria->minimumPrice }}"></label>
            <label class="field"><span>{{ trans('search::messages.price_to') }}</span><input name="max_price" inputmode="decimal" value="{{ $searchCriteria->maximumPrice }}"></label>
        </div>
        <label class="check-row"><input type="checkbox" name="available" value="1" @checked($searchCriteria->availableOnly)> {{ trans('search::messages.available_only') }}</label>

        @foreach ($searchFilters as $filter)
            <fieldset class="catalog-attribute-filter">
                <legend>{{ $filter->name }}</legend>
                <div class="catalog-filter-values">
                    @foreach ($filter->values as $value)
                        <label class="check-row"><input type="checkbox" name="attributes[{{ $filter->key }}][]" value="{{ $value->key }}" @checked(in_array($value->key, $searchCriteria->attributes[$filter->key] ?? [], true))> {{ $value->name }}</label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <div class="catalog-filter-actions">
        <button class="btn btn-primary btn-block" type="submit">{{ trans('search::messages.apply') }}</button>
        <a href="{{ $clearUrl }}">{{ trans('search::messages.clear') }}</a>
    </div>
</form>
