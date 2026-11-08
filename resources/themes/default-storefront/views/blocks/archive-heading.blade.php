@if (! empty($title))
    <div class="cms-archive-heading">
        <h1>{{ $title }}</h1>
        @if (! empty($props['show_description']) && ! empty($description))
            <div class="cms-archive-heading-description">{!! $description !!}</div>
        @endif
    </div>
@endif
