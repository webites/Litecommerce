@if ($post)
    <article class="blog-post">
        <header class="blog-post__header">
            @if ($post->categories !== [])
                <div class="blog-post__categories" aria-label="{{ trans('blog::messages.categories', [], $locale) }}">
                    @foreach ($post->categories as $category)
                        <a href="{{ route('storefront.blog.category', ['locale' => $locale, 'slug' => $category->slug]) }}">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif
            @if ($post->publishedAt)
                <time datetime="{{ $post->publishedAt }}">{{ formatDate($post->publishedAt) }}</time>
            @endif
            <h1>{{ $post->title }}</h1>
            @if (($props['show_excerpt'] ?? '1') === '1' && $post->excerpt)
                <p>{{ $post->excerpt }}</p>
            @endif
        </header>
        @if (($props['show_featured_image'] ?? '1') === '1' && $post->featuredImageUrl)
            {{-- Container width varies with the merchant's own page-builder layout around this block —
                 approximates the common 2/3-content-column case, full width below the usual container collapse. --}}
            @php(showImage($post->featuredImageUrl, $post->featuredImageSrcset, '(min-width: 900px) 780px, 100vw', $post->featuredImageWidth, $post->featuredImageHeight, ['class' => 'blog-post__featured-image', 'alt' => $post->title]))
        @endif
        <div class="blog-post__content">{!! $post->contentHtml !!}</div>
    </article>
@endif
