@extends('theme::layout')

@section('title', trans('consents::marketing.preferences_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('consents::marketing.preferences_heading') }}</h1>
        <div class="card">
            <h2>{{ trans('consents::marketing.preferences_marketing_heading') }}</h2>
            <form data-storefront="ajax" action="/customer/account/marketing-consent" method="post" data-storefront-method="put" data-storefront-success-message="{{ trans('consents::marketing.saved') }}">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <div class="field">
                    <label for="granted"><input type="checkbox" id="granted" name="granted" value="1" @checked($marketingGranted)> {{ trans('consents::marketing.statement') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ trans('consents::marketing.preferences_save') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
        <p><a href="{{ route('storefront.account.orders.index', ['locale' => $locale]) }}">{{ trans('consents::marketing.preferences_back') }}</a></p>
    </div>
@endsection
