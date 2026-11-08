@extends('theme::layout')

@section('title', trans('orders::order.order_title', ['id' => $page->order->id]))

@section('content')
    @include('theme::partials.order-detail')
@endsection
