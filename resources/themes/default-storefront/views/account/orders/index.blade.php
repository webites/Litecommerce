@extends('theme::layout')

@section('title', trans('orders::order.my_orders_title'))

@section('content')
    @php $trim = static fn (?string $value): ?string => $value === null || ! str_contains($value, '.') ? $value : rtrim(rtrim($value, '0'), '.'); @endphp

    <div class="page-head">
        <h1>{{ trans('orders::order.my_orders_title') }}</h1>
        <button type="button" class="btn btn-secondary" data-storefront-action="post" data-storefront-url="/customer/logout" data-storefront-redirect="/{locale}">{{ trans('orders::order.logout') }}</button>
    </div>

    @if (count($page->items) === 0)
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M16 3H8a2 2 0 0 0-2 2v2h12V5a2 2 0 0 0-2-2z"></path></svg>
            <h2>{{ trans('orders::order.empty_title') }}</h2>
            <p>{{ trans('orders::order.empty_text') }}</p>
            <a class="btn btn-primary" href="{{ route('storefront.catalog.index', ['locale' => $locale]) }}">{{ trans('orders::order.browse_products') }}</a>
        </div>
    @else
        <div class="card">
            <table class="order-list">
                <thead>
                    <tr><th>{{ trans('orders::order.table_number') }}</th><th>{{ trans('orders::order.table_date') }}</th><th>{{ trans('orders::order.table_status') }}</th><th>{{ trans('orders::order.table_amount') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($page->items as $order)
                        <tr>
                            <td class="mono">{{ $order->id }}</td>
                            <td>{{ $order->createdAt?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="status-stack">
                                    @include('theme::partials.order-status-badge', ['status' => $order->status, 'paymentStatus' => $order->paymentStatus])
                                </div>
                            </td>
                            <td class="mono">{{ $trim($order->totalGrossAmount) }} {{ $order->currencyCode }}</td>
                            <td><a href="{{ route('storefront.account.orders.show', ['locale' => $locale, 'order' => $order->id]) }}">{{ trans('orders::order.details_link') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('theme::partials.pagination', ['currentPage' => $page->page, 'lastPage' => (int) ceil($page->total / max(1, $page->perPage)), 'label' => trans('orders::order.pagination_label')])
    @endif

    <p class="account-footer"><a href="{{ route('storefront.account.preferences', ['locale' => $locale]) }}">{{ trans('consents::marketing.preferences_link') }}</a> · <a href="{{ route('storefront.account.delete', ['locale' => $locale]) }}">{{ trans('identity::account_deletion.link') }}</a></p>
@endsection
