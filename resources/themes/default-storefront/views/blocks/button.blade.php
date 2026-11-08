@if (! empty($props['label']) && ! empty($props['url']))
    <div class="cms-button">
        <a
            class="btn btn-{{ $props['style'] ?? 'primary' }}"
            href="{{ $props['url'] }}"
            @if (! empty($props['open_in_new_tab'])) target="_blank" rel="noopener" @endif
        >{{ $props['label'] }}</a>
    </div>
@endif
