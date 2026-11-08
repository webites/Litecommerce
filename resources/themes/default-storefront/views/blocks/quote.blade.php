@if (! empty($props['text']))
    <blockquote class="cms-quote">
        <p>{{ $props['text'] }}</p>
        @if (! empty($props['citation']))
            <cite>— {{ $props['citation'] }}</cite>
        @endif
    </blockquote>
@endif
