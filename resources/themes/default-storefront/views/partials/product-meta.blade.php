{{-- $product: ProductViewData — brand and categories, product-level, never variant-reactive. --}}
@if ($product->brandName)
    <p class="brand">{{ $product->brandName }}</p>
@endif

@if (count($product->categories) > 0)
    <p class="categories">
        {{ trans('catalog::product.categories_label') }}
        @foreach ($product->categories as $category)
            <a href="{{ route('storefront.cms.show', ['locale' => $locale, 'slug' => $category->slug]) }}">{{ $category->name }}</a>@if (! $loop->last), @endif
        @endforeach
    </p>
@endif
