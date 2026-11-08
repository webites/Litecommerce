@extends('theme::layout')

@section('title', trans('orders::order.thank_you_title'))

@section('content')
    @include('theme::partials.order-detail', ['heading' => trans('orders::order.thank_you_heading')])
@endsection
