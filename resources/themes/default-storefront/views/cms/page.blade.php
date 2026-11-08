@extends('theme::layout')

@section('title', $page->seoTitle ?: $page->title)

@if ($page->seoDescription)
    @section('meta_description', $page->seoDescription)
@endif

@section('content')
    @if (trim($blocksHtml) === '')
        <div class="empty-state">
            <h2>{{ $page->title }}</h2>
        </div>
    @else
        {!! $blocksHtml !!}
    @endif
@endsection
