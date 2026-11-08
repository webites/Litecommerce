@if ($lastPage > 1)
    <nav class="pagination" aria-label="{{ $label }}">
        @for ($paginationPage = 1; $paginationPage <= $lastPage; $paginationPage++)
            @if ($paginationPage === $currentPage)
                <span class="is-active" aria-current="page">{{ $paginationPage }}</span>
            @else
                <a href="{{ request()->fullUrlWithQuery([($queryKey ?? 'page') => $paginationPage]) }}">{{ $paginationPage }}</a>
            @endif
        @endfor
    </nav>
@endif
