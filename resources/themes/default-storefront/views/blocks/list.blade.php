@if (! empty($props['items']))
    @php $tag = ($props['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul'; @endphp
    <{{ $tag }} class="cms-list">
        @foreach ($props['items'] as $item)
            <li>{{ $item['text'] ?? '' }}</li>
        @endforeach
    </{{ $tag }}>
@endif
