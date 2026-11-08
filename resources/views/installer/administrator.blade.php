@extends('layouts.minimal')

@section('title', __('installer::messages.administrator.title'))
@section('intro', __('installer::messages.administrator.intro'))
@section('step', '2')

@section('content')
    <form class="page" method="POST" action="{{ route('install.administrator.store') }}">
        @csrf

        <div class="field">
            <label for="name">{{ __('installer::messages.administrator.name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
        </div>

        <div class="field">
            <label for="email">{{ __('installer::messages.administrator.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
        </div>

        <div class="field">
            <label for="password">{{ __('installer::messages.administrator.password') }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <p class="hint">{{ __('installer::messages.administrator.password_hint') }}</p>
        </div>

        <div class="field">
            <label for="password_confirmation">{{ __('installer::messages.administrator.password_confirmation') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>

        <div class="actions">
            <button type="submit" class="button">{{ __('installer::messages.continue') }}</button>
        </div>
    </form>
@endsection
