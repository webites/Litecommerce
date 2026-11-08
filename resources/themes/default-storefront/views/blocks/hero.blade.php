<section class="cms-hero cms-hero--{{ $props['text_theme'] ?? 'light' }}" @if ($backgroundUrl) style="background-image: url('{{ $backgroundUrl }}');" @endif>
    @if (! empty($props['title']))
        <h1>{{ $props['title'] }}</h1>
    @endif
    @if (! empty($props['subtitle']))
        <p>{{ $props['subtitle'] }}</p>
    @endif
    @if (! empty($props['cta_label']) && ! empty($props['cta_url']))
        <a class="btn btn-primary" href="{{ $props['cta_url'] }}">{{ $props['cta_label'] }}</a>
    @endif
</section>
