{{--
    $fieldName is the real form field name (e.g. "billing_address[country_code]")
    so the generic form serializer in runtime.js keeps working unchanged — the
    visible search <input> below carries no name at all, only the hidden one
    does. No <option>s are rendered here: the list is fetched once from
    GET /checkout/countries by data-storefront-country-select in runtime.js
    (async, per the product decision not to block checkout's first paint on
    ~195 rows), then filtered client-side as the visitor types.
--}}
<div class="sf-country-select" data-storefront-country-select data-storefront-country-no-results="{{ trans('cart::checkout.country_no_results') }}">
    <input type="text" class="sf-country-search" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off"
           placeholder="{{ trans('cart::checkout.country_search_placeholder') }}">
    <input type="hidden" name="{{ $fieldName }}" data-storefront-country-value>
    <ul class="sf-country-options" role="listbox" hidden></ul>
</div>
