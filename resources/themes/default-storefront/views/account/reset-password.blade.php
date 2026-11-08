@extends('theme::layout')

@section('title', trans('identity::customer_auth.reset_password_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::customer_auth.reset_password_heading') }}</h1>
        <div class="card">
            <form data-storefront="ajax" action="/customer/reset-password" method="post" data-storefront-redirect="/{locale}/account/login">
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field"><label for="email">{{ trans('identity::customer_auth.email_label') }}</label><input type="email" id="email" name="email" value="{{ $email }}" required autocomplete="email"></div>
                <div class="field"><label for="password">{{ trans('identity::customer_auth.password_label') }}</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
                <div class="field"><label for="password_confirmation">{{ trans('identity::customer_auth.password_confirmation_label') }}</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
                <button type="submit" class="btn btn-primary">{{ trans('identity::customer_auth.reset_password_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
    </div>
@endsection
