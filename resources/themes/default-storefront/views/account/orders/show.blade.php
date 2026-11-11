@extends('theme::layout')

@section('title', trans('orders::order.order_title', ['id' => $page->order->number]))

@section('content')
    @include('theme::partials.order-detail')
@endsection
