@php
    $items = $posts ?? (is_array($blogPage ?? null) ? ($blogPage['items'] ?? []) : []);
    $layout = in_array($props['layout'] ?? 'grid', ['grid', 'list', 'carousel'], true) ? ($props['layout'] ?? 'grid') : 'grid';
    // Matches each layout's own CSS sizing (.blog-grid/.blog-post-list--{list,carousel} in theme.css).
    $imageSizes = match ($layout) {
        'list' => '88px',
        'carousel' => '(min-width: 640px) 340px, 82vw',
        default => '(min-width: 900px) 33vw, (min-width: 640px) 50vw, 100vw',
    };
@endphp

@if ($items !== [])
    <section @if ($layout === 'carousel') data-storefront-carousel @endif class="blog-post-collection blog-post-collection--{{ $layout }}">
        @if ($layout === 'carousel')
            <div class="blog-carousel__controls">
                <button type="button" data-storefront-carousel-prev aria-label="{{ trans('blog::messages.previous_posts', [], $locale) }}">←</button>
                <button type="button" data-storefront-carousel-next aria-label="{{ trans('blog::messages.next_posts', [], $locale) }}">→</button>
            </div>
        @endif

        <div @if ($layout === 'carousel') data-storefront-carousel-track tabindex="0" @endif class="blog-post-list blog-post-list--{{ $layout }} @if ($layout === 'grid') blog-grid @endif">
            @foreach ($items as $post)
                <article class="blog-card">
                    @if ($post->featuredImageUrl)
                        <a class="blog-card__image-link" href="{{ route('storefront.blog.show', ['locale' => $locale, 'slug' => $post->slug]) }}" tabindex="-1" aria-hidden="true">
                            @php(showImage($post->featuredImageUrl, $post->featuredImageSrcset, $imageSizes, $post->featuredImageWidth, $post->featuredImageHeight, ['class' => 'blog-card__image', 'alt' => '', 'loading' => 'lazy']))
                        </a>
                    @endif
                    <div class="blog-card__body">
                        @if ($post->publishedAt)
                            <time datetime="{{ $post->publishedAt }}">{{ formatDate($post->publishedAt) }}</time>
                        @endif
                        <h2><a href="{{ route('storefront.blog.show', ['locale' => $locale, 'slug' => $post->slug]) }}">{{ $post->title }}</a></h2>
                        @if (($props['show_excerpt'] ?? '1') === '1' && $post->excerpt)
                            <p>{{ $post->excerpt }}</p>
                        @endif
                        @if ($layout !== 'list' && $post->categories !== [])
                            <div class="blog-card__categories">
                                @foreach ($post->categories as $category)
                                    <a href="{{ route('storefront.blog.category', ['locale' => $locale, 'slug' => $category->slug]) }}">{{ $category->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @if (isset($blogPage) && is_array($blogPage))
        @include('theme::partials.pagination', ['currentPage' => (int) $blogPage['current_page'], 'lastPage' => (int) $blogPage['last_page'], 'label' => trans('blog::messages.pagination', [], $locale)])
    @endif
@elseif (isset($blogPage))
    <div class="empty-state">
        <p>{{ trans('blog::messages.no_posts', [], $locale) }}</p>
    </div>
@endif
