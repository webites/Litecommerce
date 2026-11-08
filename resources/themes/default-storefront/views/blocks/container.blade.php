@php $count = max(1, min(4, (int) ($props['columns'] ?? 2))); @endphp
<div
    class="cms-container cms-container--{{ $props['layout'] ?? 'flex' }} cms-container--gap-{{ $props['gap'] ?? 'md' }} cms-container--ratio-{{ $props['ratio'] ?? 'equal' }}"
    style="--cms-container-columns: {{ $count }};"
>
    @for ($i = 0; $i < $count; $i++)
        <div class="cms-container-column">{!! $columns[$i] ?? '' !!}</div>
    @endfor
</div>
