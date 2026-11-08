@if (! empty($props['text']))
    @php $level = max(1, min(4, (int) ($props['level'] ?? 2))); @endphp
    <h{{ $level }} class="cms-heading">{{ $props['text'] }}</h{{ $level }}>
@endif
