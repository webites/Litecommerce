@extends('theme::layout')

@section('title', trans('identity::customer_auth.register_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::customer_auth.register_heading') }}</h1>
        <p class="subtitle">{{ trans('identity::customer_auth.register_subtitle') }}</p>

        <div class="card">
            <form data-storefront="ajax" action="/customer/register" method="post"
                  data-storefront-redirect="/{locale}/account/orders">
                <div class="field">
                    <label for="email">{{ trans('identity::customer_auth.email_label') }}</label>
                    <input type="email" id="email" name="email" required autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">{{ trans('identity::customer_auth.password_label') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <span class="field-error" data-storefront-error-for="password"></span>
                </div>
                <div class="field">
                    <label for="password_confirmation">{{ trans('identity::customer_auth.password_confirmation_label') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
                <input type="hidden" name="locale" value="{{ $locale }}">
                <label class="check-row"><input type="checkbox" name="marketing_consent"> {{ trans('consents::marketing.statement') }}</label>
                <button type="submit" class="btn btn-primary">{{ trans('identity::customer_auth.register_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>

        <p class="switch">{{ trans('identity::customer_auth.has_account_prompt') }} <a href="{{ route('storefront.account.login', ['locale' => $locale]) }}">{{ trans('identity::customer_auth.login_link') }}</a></p>
    </div>
@endsection
