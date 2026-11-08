@extends('layouts.minimal')

@section('title', __('installer::messages.finalize.title'))
@section('intro', __('installer::messages.finalize.intro'))
@section('step', '3')

@section('content')
    <form class="page" method="POST" action="{{ route('install.finalize.store') }}">
        @csrf
        <div class="actions">
            <button type="submit" class="button">{{ __('installer::messages.finalize.install') }}</button>
        </div>
        <p class="hint">{{ __('installer::messages.finalize.hint') }}</p>
    </form>
@endsection
