@extends('theme::layout')

@section('title', trans('identity::customer_auth.login_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::customer_auth.login_heading') }}</h1>
        <p class="subtitle">{{ trans('identity::customer_auth.login_subtitle') }}</p>

        <div class="card">
            <form data-storefront="ajax" action="/customer/login" method="post"
                  data-storefront-redirect="/{locale}/account/orders">
                <div class="field">
                    <label for="email">{{ trans('identity::customer_auth.email_label') }}</label>
                    <input type="email" id="email" name="email" required autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">{{ trans('identity::customer_auth.password_label') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary">{{ trans('identity::customer_auth.login_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
            <p class="switch"><a href="{{ route('storefront.account.password.request', ['locale' => $locale]) }}">{{ trans('identity::customer_auth.forgot_password_link') }}</a></p>
        </div>

        <p class="switch">{{ trans('identity::customer_auth.no_account_prompt') }} <a href="{{ route('storefront.account.register', ['locale' => $locale]) }}">{{ trans('identity::customer_auth.register_link') }}</a></p>
    </div>
@endsection
