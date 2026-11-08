@extends('layouts.minimal')

@section('title', __('installer::messages.database.title'))
@section('intro', __('installer::messages.database.intro'))
@section('step', '1')

@section('content')
    <form method="POST" action="{{ route('install.database.store') }}">
        @csrf

        <label for="host">{{ __('installer::messages.database.host') }}</label>
        <input type="text" id="host" name="host" value="{{ old('host', 'localhost') }}" required>

        <label for="port">{{ __('installer::messages.database.port') }}</label>
        <input type="text" id="port" name="port" value="{{ old('port', '3306') }}" required>

        <label for="database">{{ __('installer::messages.database.database') }}</label>
        <input type="text" id="database" name="database" value="{{ old('database') }}" required>

        <label for="username">{{ __('installer::messages.database.username') }}</label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="off" required>

        <label for="password">{{ __('installer::messages.database.password') }}</label>
        <input type="password" id="password" name="password" autocomplete="new-password">

        <div class="actions">
            <button type="submit" class="primary">{{ __('installer::messages.continue') }}</button>
        </div>
    </form>
@endsection
