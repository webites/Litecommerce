@extends('layouts.minimal')

@section('title', __('installer::messages.database.title'))
@section('intro', __('installer::messages.database.intro'))
@section('step', '1')

@section('content')
    <form class="page" method="POST" action="{{ route('install.database.store') }}">
        @csrf

        <div class="field">
            <label for="host">{{ __('installer::messages.database.host') }}</label>
            <input type="text" id="host" name="host" value="{{ old('host', 'localhost') }}" required>
        </div>

        <div class="field">
            <label for="port">{{ __('installer::messages.database.port') }}</label>
            <input type="text" id="port" name="port" value="{{ old('port', '3306') }}" required>
        </div>

        <div class="field">
            <label for="database">{{ __('installer::messages.database.database') }}</label>
            <input type="text" id="database" name="database" value="{{ old('database') }}" required>
        </div>

        <div class="field">
            <label for="username">{{ __('installer::messages.database.username') }}</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="off" required>
        </div>

        <div class="field">
            <label for="password">{{ __('installer::messages.database.password') }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password">
        </div>

        <div class="actions">
            <button type="submit" class="button">{{ __('installer::messages.continue') }}</button>
        </div>
    </form>
@endsection
