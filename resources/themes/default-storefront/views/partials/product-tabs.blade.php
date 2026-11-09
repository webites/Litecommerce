{{-- Tab switching is a plain radio group + data-storefront-show-if panels, keyed by name="product_tab" — same engine primitive as the variant picker. --}}
@php
    $pluginTabs = app(\Litecommerce\Modules\Storefront\Contracts\ProductTabRegistry::class)->all(
        new \Litecommerce\Modules\Storefront\Contracts\ProductTabContext($product, $axes, $offers),
    );
@endphp
<div class="product-tabs">
    <div class="tab-list" role="tablist">
        <label class="tab-btn"><input type="radio" name="product_tab" value="opis" checked>{{ trans('catalog::product.description') }}</label>
        <label class="tab-btn"><input type="radio" name="product_tab" value="specyfikacja">{{ trans('catalog::product.specification') }}</label>
        @foreach ($pluginTabs as $tab)
            <label class="tab-btn"><input type="radio" name="product_tab" value="{{ $tab->id }}">{{ $tab->label }}</label>
        @endforeach
    </div>

    <div class="tab-panel description" data-storefront-show-if="product_tab:opis">
        @if (count($axes) > 0)
            <div data-storefront-show-if-container>
                @foreach ($offers as $offer)
                    @php $isInitiallySelected = ($preselectedOptionSignature ?? null) === $offer->optionSignature; @endphp
                    <div data-storefront-show-if="{{ $offer->optionSignature }}" @if (! $isInitiallySelected) hidden @endif>
                        @if ($offer->descriptionHtml !== null && trim($offer->descriptionHtml) !== '')
                            {!! $offer->descriptionHtml !!}
                        @else
                            <p class="muted">{{ trans('catalog::product.no_description') }}</p>
                        @endif
                    </div>
                @endforeach

                @if (($preselectedOptionSignature ?? null) === null)
                    <div data-storefront-show-if-fallback>
                        @if ($product->descriptionHtml)
                            {!! $product->descriptionHtml !!}
                        @else
                            <p class="muted">{{ trans('catalog::product.no_description') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @elseif ($product->descriptionHtml)
            {!! $product->descriptionHtml !!}
        @else
            <p class="muted">{{ trans('catalog::product.no_description') }}</p>
        @endif
        <div class="plugin-slot">@storefrontSlot('product.after_description')</div>
    </div>

    <div class="tab-panel" data-storefront-show-if="product_tab:specyfikacja" hidden>
        @foreach ($offers as $offer)
            <table class="spec-table" @if ($offer->optionSignature !== '') data-storefront-show-if="{{ $offer->optionSignature }}" hidden @endif>
                <tbody>
                    <tr><th>SKU</th><td>{{ $offer->sku }}</td></tr>
                    @if ($offer->gtin !== '')
                        <tr><th>GTIN</th><td>{{ $offer->gtin }}</td></tr>
                    @endif
                    @if ($offer->mpn !== '')
                        <tr><th>MPN</th><td>{{ $offer->mpn }}</td></tr>
                    @endif
                </tbody>
            </table>
        @endforeach

        @foreach ($product->specification as $group)
            @if ($group->name !== null || count($product->specification) > 1)
                <h3 class="spec-group">{{ $group->name ?? trans('catalog::product.specification_other') }}</h3>
            @endif
            <table class="spec-table">
                <tbody>
                    @foreach ($group->items as $item)
                        <tr><th>{{ $item->name }}</th><td>{{ $item->value }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

    @foreach ($pluginTabs as $tab)
        <div class="tab-panel" data-storefront-show-if="product_tab:{{ $tab->id }}" hidden>{!! $tab->html !!}</div>
    @endforeach
</div>
