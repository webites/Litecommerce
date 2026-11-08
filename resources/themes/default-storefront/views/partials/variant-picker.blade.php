{{--
    Only the axis selector radio groups; price/SKU/add-to-cart live in sibling
    partials keyed by the same optionSignature. data-storefront-axis-slug/
    -value-slug feed bindVariantUrlSync() in runtime.js, which keeps the
    visible URL in sync with the picker without a page reload. $preselectedOptionValues
    (axis key => value key, from a variant deep-link URL) checks the matching
    radio server-side; empty/absent on the base product URL, same as today.
--}}
@foreach ($axes as $axis)
    <div class="option-group">
        <p class="option-group-label">{{ $axis->name }}</p>
        <div class="option-values">
            @foreach ($axis->values as $value)
                <label class="@if ($axis->isColor) opt-swatch @else opt-pill @endif" title="{{ $value->name }}"
                       @if ($axis->isColor) style="--swatch-color: {{ $value->colorHex ?? 'transparent' }}" @endif>
                    <input type="radio" name="{{ $axis->key }}" value="{{ $value->key }}"
                           data-storefront-axis-slug="{{ $axis->slug }}" data-storefront-value-slug="{{ $value->slug }}"
                           @checked(($preselectedOptionValues[$axis->key] ?? null) === $value->key)>
                    <span>{{ $value->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach
