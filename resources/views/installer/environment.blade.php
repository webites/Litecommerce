@extends('layouts.minimal')

@section('title', __('installer::messages.environment.title'))
@section('intro', __('installer::messages.environment.intro'))
@section('step', '0')

@section('content')
    <ul class="checks">
        <li>
            <span class="badge {{ $result->phpVersionSatisfied ? 'ok' : 'fail' }}">{{ $result->phpVersionSatisfied ? '✓' : '!' }}</span>
            <div>
                <div class="check-title">{{ __('installer::messages.environment.php') }}</div>
                <div class="check-detail">{{ $result->phpVersionSatisfied ? __('installer::messages.environment.php_ok', ['version' => PHP_VERSION]) : __('installer::messages.environment.php_missing') }}</div>
            </div>
        </li>
        <li>
            <span class="badge {{ $result->missingExtensions === [] ? 'ok' : 'fail' }}">{{ $result->missingExtensions === [] ? '✓' : '!' }}</span>
            <div>
                <div class="check-title">{{ __('installer::messages.environment.extensions') }}</div>
                <div class="check-detail">{{ $result->missingExtensions === [] ? __('installer::messages.environment.extensions_ok') : __('installer::messages.environment.extensions_missing', ['list' => implode(', ', $result->missingExtensions)]) }}</div>
            </div>
        </li>
        <li>
            <span class="badge {{ $result->unwritablePaths === [] ? 'ok' : 'fail' }}">{{ $result->unwritablePaths === [] ? '✓' : '!' }}</span>
            <div>
                <div class="check-title">{{ __('installer::messages.environment.paths') }}</div>
                <div class="check-detail">{{ $result->unwritablePaths === [] ? __('installer::messages.environment.paths_ok') : __('installer::messages.environment.paths_missing', ['list' => implode(', ', $result->unwritablePaths)]) }}</div>
            </div>
        </li>
    </ul>

    @if ($result->satisfied())
        <div class="alert success">{{ __('installer::messages.environment.satisfied') }}</div>
        <div class="actions">
            <a class="button" href="{{ route('install.database.show') }}">{{ __('installer::messages.continue') }}</a>
        </div>
    @else
        <div class="alert danger">{{ __('installer::messages.environment.fix') }}</div>
        <div class="actions">
            <a class="button outline" href="{{ route('install.environment') }}">{{ __('installer::messages.environment.refresh') }}</a>
        </div>
    @endif
@endsection
