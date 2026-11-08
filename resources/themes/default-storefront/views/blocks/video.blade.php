@if ($youtubeId ?? null)
    <div class="cms-video">
        <div class="cms-video-frame">
            <iframe
                src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}"
                title="{{ $props['caption'] ?? 'YouTube' }}"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
        @if (! empty($props['caption']))
            <p class="cms-video-caption">{{ $props['caption'] }}</p>
        @endif
    </div>
@endif
