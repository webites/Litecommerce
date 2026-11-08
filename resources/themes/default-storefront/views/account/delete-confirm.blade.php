@extends('theme::layout')

@section('title', trans('identity::account_deletion.confirm_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::account_deletion.confirm_heading') }}</h1>
        <p class="subtitle">{{ trans('identity::account_deletion.confirm_text') }}</p>
        <div class="card">
            <form data-storefront="ajax" action="/customer/account/deletion/confirm" method="post" data-storefront-redirect="/{locale}" data-storefront-success-message="{{ trans('identity::account_deletion.deleted') }}">
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <button type="submit" class="btn btn-primary">{{ trans('identity::account_deletion.confirm_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
    </div>
@endsection
