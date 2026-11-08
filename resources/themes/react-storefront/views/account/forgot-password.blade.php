@extends('theme::layout')

@section('title', trans('identity::customer_auth.forgot_password_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::customer_auth.forgot_password_heading') }}</h1>
        <p class="subtitle">{{ trans('identity::customer_auth.forgot_password_subtitle') }}</p>
        <div class="card">
            <form data-storefront="ajax" action="/customer/forgot-password" method="post">
                <div class="field"><label for="email">{{ trans('identity::customer_auth.email_label') }}</label><input type="email" id="email" name="email" required autocomplete="email"></div>
                <button type="submit" class="btn btn-primary">{{ trans('identity::customer_auth.send_reset_link') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
    </div>
@endsection
