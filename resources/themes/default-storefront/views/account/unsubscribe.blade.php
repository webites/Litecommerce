@extends('theme::layout')

@section('title', trans('consents::marketing.unsubscribe_title'))

@section('content')
    <div class="auth-wrap">
        <h1>{{ trans('consents::marketing.unsubscribe_heading') }}</h1>
        <p class="subtitle">{{ trans('consents::marketing.unsubscribe_text') }}</p>
        <div class="card">
            <form data-storefront="ajax" action="/marketing/unsubscribe/{{ $token }}" method="post" data-storefront-success-message="{{ trans('consents::marketing.unsubscribed') }}">
                <button type="submit" class="btn btn-primary">{{ trans('consents::marketing.unsubscribe_button') }}</button>
                <p data-storefront-message role="status"></p>
            </form>
        </div>
    </div>
@endsection
