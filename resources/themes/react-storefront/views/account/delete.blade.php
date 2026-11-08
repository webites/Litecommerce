@extends('theme::layout')

@section('title', trans('identity::account_deletion.title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('identity::account_deletion.heading') }}</h1>
        <p class="subtitle">{{ trans('identity::account_deletion.intro') }}</p>
        <div class="card">
            <h2>{{ trans('identity::account_deletion.kept_heading') }}</h2>
            <p>{{ trans('identity::account_deletion.kept_orders') }}</p>
            <form data-storefront="ajax" action="/customer/account/deletion" method="post" data-storefront-success-message="{{ trans('identity::account_deletion.link_sent') }}">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <div class="field">
                    <label for="password">{{ trans('identity::account_deletion.password_label') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <small>{{ trans('identity::account_deletion.password_hint') }}</small>
                </div>
                <button type="submit" class="btn btn-primary">{{ trans('identity::account_deletion.send_link') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
        <p><a href="{{ route('storefront.account.orders.index', ['locale' => $locale]) }}">{{ trans('identity::account_deletion.back_link') }}</a></p>
    </div>
@endsection
