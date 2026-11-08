@extends('theme::layout')

@section('title', $post->seoTitle ?: $post->title)

@if ($post->seoDescription ?: $post->excerpt)
    @section('meta_description', $post->seoDescription ?: $post->excerpt)
@endif

@section('content')
    {!! $blocksHtml !!}
@endsection
