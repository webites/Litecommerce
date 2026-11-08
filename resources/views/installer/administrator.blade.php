@extends('layouts.minimal')

@section('title', __('installer::messages.administrator.title'))
@section('intro', __('installer::messages.administrator.intro'))
@section('step', '2')

@section('content')
    <form method="POST" action="{{ route('install.administrator.store') }}">
        @csrf

        <label for="name">{{ __('installer::messages.administrator.name') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>

        <label for="email">{{ __('installer::messages.administrator.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>

        <label for="password">{{ __('installer::messages.administrator.password') }}</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>
        <p class="hint">{{ __('installer::messages.administrator.password_hint') }}</p>

        <label for="password_confirmation">{{ __('installer::messages.administrator.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>

        <div class="actions">
            <button type="submit" class="primary">{{ __('installer::messages.continue') }}</button>
        </div>
    </form>
@endsection
