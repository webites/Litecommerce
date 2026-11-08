@if (count($axes) > 0)
    <div data-storefront-show-if-container>
        @foreach ($offers as $offer)
            @php $isInitiallySelected = ($preselectedOptionSignature ?? null) === $offer->optionSignature; @endphp
            @if ($offer->shortDescription !== null && trim($offer->shortDescription) !== '')
                <p class="short-description" data-storefront-show-if="{{ $offer->optionSignature }}" @if (! $isInitiallySelected) hidden @endif>{{ $offer->shortDescription }}</p>
            @else
                <span data-storefront-show-if="{{ $offer->optionSignature }}" @if (! $isInitiallySelected) hidden @endif></span>
            @endif
        @endforeach

        @if (($preselectedOptionSignature ?? null) === null && $product->shortDescription)
            <p class="short-description" data-storefront-show-if-fallback>{{ $product->shortDescription }}</p>
        @endif
    </div>
@elseif ($product->shortDescription)
    <p class="short-description">{{ $product->shortDescription }}</p>
@endif
