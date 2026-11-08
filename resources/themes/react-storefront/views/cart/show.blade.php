@extends('theme::layout')

@section('title', trans('cart::cart.title'))

@section('content')
    <h1>{{ trans('cart::cart.heading') }}</h1>

    {{-- The whole cart is one React island instead of a data-storefront-load region. --}}
    <div id="cart-checkout-root" data-step="cart"></div>
@endsection
