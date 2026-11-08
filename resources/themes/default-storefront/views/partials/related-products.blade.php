@if (count($items) > 0)
    <section class="related-products">
        <h2>{{ $heading }}</h2>
        <div class="product-grid">
            @foreach ($items as $item)
                @include('theme::partials.product-card')
            @endforeach
        </div>
    </section>
@endif
