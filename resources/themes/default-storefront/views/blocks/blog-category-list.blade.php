@if ($categories !== [])
    <nav class="blog-category-list blog-category-list--{{ ($props['layout'] ?? 'list') === 'chips' ? 'chips' : 'list' }}" aria-label="{{ trans('blog::messages.categories', [], $locale) }}">
        @foreach ($categories as $category)
            <a href="{{ route('storefront.blog.category', ['locale' => $locale, 'slug' => $category->slug]) }}">{{ $category->name }}</a>
        @endforeach
    </nav>
@endif
