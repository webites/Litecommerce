@php
    // Matches .sf-main's own max-width (theme.css) for the 'contained' block width; the
    // 'full' option really does span the viewport, so 100vw is exact there, not a guess.
    $bannerSizes = ($props['width'] ?? 'contained') === 'full' ? '100vw' : '(min-width: 1180px) 1180px, 100vw';
@endphp
@if ($imageUrl)
    <figure class="cms-image-banner">
        @if (! empty($props['link_url']))
            <a href="{{ $props['link_url'] }}">@php(showImage($imageUrl, $imageSrcset ?? null, $bannerSizes, $imageWidth ?? null, $imageHeight ?? null, ['alt' => $props['alt'] ?? '']))</a>
        @else
            @php(showImage($imageUrl, $imageSrcset ?? null, $bannerSizes, $imageWidth ?? null, $imageHeight ?? null, ['alt' => $props['alt'] ?? '']))
        @endif
        @if (! empty($props['caption']))
            <figcaption>{{ $props['caption'] }}</figcaption>
        @endif
    </figure>
@endif
