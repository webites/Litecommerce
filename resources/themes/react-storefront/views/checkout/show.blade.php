@extends('theme::layout')

@section('title', trans('cart::checkout.title'))

@section('content')
    <p class="crumb"><a href="{{ route('storefront.cart.show', ['locale' => $locale]) }}">{{ trans('cart::checkout.back_to_cart') }}</a></p>
    <h1>{{ trans('cart::checkout.heading') }}</h1>

    {{-- Same island as cart/show.blade.php; bootstraps its own checkout draft on mount. --}}
    <div id="cart-checkout-root" data-step="checkout"></div>
@endsection
