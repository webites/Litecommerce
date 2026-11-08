@php
    $order = $page->order;
    $payment = $page->latestPayment;
    // OrderDetailData exposes raw decimal(20,6) strings — same trailing-zero trim the panel applies at its own display boundary.
    $trim = static fn (?string $value): ?string => $value === null || ! str_contains($value, '.') ? $value : rtrim(rtrim($value, '0'), '.');
    $shippingAddress = $order->shippingAddress ?? $order->billingAddress;
    $paymentAttemptLabels = trans('orders::order.payment_status');
@endphp

@if (isset($heading))
    <div class="banner banner-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <div>
            <h2>{{ $heading }}</h2>
            <p>{{ trans('orders::order.confirmation_email_note') }}</p>
            <div class="meta-row">
                {{ trans('orders::order.order_number') }} <span class="order-id">#{{ $order->id }}</span>
                @include('theme::partials.order-status-badge', ['status' => $order->status, 'paymentStatus' => $order->paymentStatus])
            </div>
        </div>
    </div>
@else
    <p class="crumb"><a href="{{ route('storefront.account.orders.index', ['locale' => $locale]) }}">{{ trans('orders::order.back_to_my_orders') }}</a></p>
    <div class="page-head" style="display: block;">
        <h1>{{ trans('orders::order.order_number') }} <span class="order-id">#{{ $order->id }}</span></h1>
        <div class="meta-row">
            @include('theme::partials.order-status-badge', ['status' => $order->status, 'paymentStatus' => $order->paymentStatus])
            @if ($order->createdAt !== null)
                <span>{{ trans('orders::order.placed_on', ['date' => $order->createdAt->format('Y-m-d H:i')]) }}</span>
            @endif
        </div>
    </div>
@endif

<div class="card">
    <h3>{{ trans('orders::order.order_lines_heading') }}</h3>
    <table class="order-lines">
        {{-- OrderLineDetailData only carries the line total, not a separate per-unit price, so this stays two money columns. --}}
        <thead><tr><th>{{ trans('cart::cart.table_product') }}</th><th>{{ trans('cart::cart.table_quantity') }}</th><th>{{ trans('orders::order.total') }}</th></tr></thead>
        <tbody>
            @foreach ($order->lines as $line)
                <tr>
                    <td>
                        <div class="li-name">{{ $line->displayName }}</div>
                        <div class="li-sku">SKU: {{ $line->sku }}</div>
                    </td>
                    <td class="mono">{{ $trim($line->quantity) }}</td>
                    <td class="mono">{{ $trim($line->grossAmount) }}</td>
                </tr>
            @endforeach
            @if ($order->deliveryName !== null)
                <tr>
                    <td><div class="li-name">{{ $order->deliveryName }}{{ trans('orders::order.delivery_line_suffix') }}</div></td>
                    <td class="mono">1</td>
                    <td class="mono">{{ $trim($order->deliveryGrossAmount) }}</td>
                </tr>
            @endif
            @foreach ($order->adjustmentTotals() as $adjustment)
                <tr>
                    <td><div class="li-name">{{ $adjustment['label'] }}</div></td>
                    <td class="mono">1</td>
                    <td class="mono">{{ $trim($adjustment['gross_amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="card-pad" style="padding-top: 14px;">
        <div class="summary-total"><span>{{ trans('orders::order.total') }}</span><span class="val">{{ $trim($order->totalGrossAmount) }} {{ $order->currencyCode }}</span></div>
    </div>
</div>

<div class="two-col">
    <div class="card card-pad">
        <p class="label-sm">{{ trans('orders::order.shipping_address') }}</p>
        <address>
            {{ $shippingAddress['recipient_name'] ?? '' }}<br>
            {{ $shippingAddress['address_line_1'] ?? '' }}@if (! empty($shippingAddress['address_line_2'])), {{ $shippingAddress['address_line_2'] }}@endif<br>
            {{ $shippingAddress['postal_code'] ?? '' }} {{ $shippingAddress['city'] ?? '' }}<br>
            {{ $shippingAddress['country_code'] ?? '' }}@if (! empty($shippingAddress['phone'])) · {{ $shippingAddress['phone'] }}@endif
        </address>
    </div>
    <div class="card card-pad">
        <p class="label-sm">{{ trans('orders::order.delivery_method') }}</p>
        <p style="margin: 0; font-size: 14px;">{{ $order->deliveryName ?? trans('orders::order.no_delivery_digital') }}</p>
    </div>
</div>

@if ($payment !== null)
    <div class="card card-pad">
        <p class="label-sm">{{ trans('orders::order.payment') }}</p>
        <p style="margin: 0; font-size: 14px;">
            {{ trans('orders::order.payment_method_status_line', ['method' => $payment->methodId, 'status' => $paymentAttemptLabels[$payment->status->value] ?? $payment->status->value]) }}
        </p>

        @if ($payment->redirectUrl !== null)
            <p style="margin-top: 10px;"><a class="btn btn-primary" href="{{ $payment->redirectUrl }}">{{ trans('orders::order.go_to_payment') }}</a></p>
        @endif

        @if (isset($payment->metadata['account_number']))
            <div class="bank-box">
                <div class="row"><span>{{ trans('orders::order.account_number') }}</span><strong>{{ $payment->metadata['account_number'] }}</strong></div>
                @if (isset($payment->metadata['beneficiary_name']))
                    <div class="row"><span>{{ trans('orders::order.beneficiary') }}</span><strong style="font-family: inherit;">{{ $payment->metadata['beneficiary_name'] }}</strong></div>
                @endif
                @if (isset($payment->metadata['transfer_title']))
                    <div class="row"><span>{{ trans('orders::order.transfer_title') }}</span><strong>{{ $payment->metadata['transfer_title'] }}</strong></div>
                @endif
            </div>
        @endif
    </div>
@endif

@if ($order->deliveryPickupPointSnapshot !== null)
    <div class="card card-pad">
        <p class="label-sm">{{ trans('orders::order.pickup_point') }}</p>
        <p style="margin: 0; font-size: 14px;">{{ $order->deliveryPickupPointSnapshot['name'] ?? '' }}</p>
        <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--ink-faint);">{{ $order->deliveryPickupPointSnapshot['address_line_1'] ?? '' }}, {{ $order->deliveryPickupPointSnapshot['city'] ?? '' }}</p>
        @if ($order->pickup !== null)
            <p class="pickup-state" data-pickup-state="{{ $order->pickup->state->value }}" style="margin: 10px 0 0; font-size: 14px;"><strong>{{ trans('orders::order.pickup_state.'.$order->pickup->state->value) }}</strong></p>
            @if ($order->pickup->state === \Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState::ReadyForPickup)
                @if ($order->pickup->readyAt !== null)
                    <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--ink-faint);">{{ trans('orders::order.pickup_ready_since', ['date' => $order->pickup->readyAt->format('Y-m-d H:i')]) }}</p>
                @endif
                @if ($order->pickup->deadlineAt !== null)
                    <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--ink-faint);">{{ trans('orders::order.pickup_deadline', ['date' => $order->pickup->deadlineAt->format('Y-m-d H:i')]) }}@if ($order->pickup->lapsesWhenUncollected) {{ trans('orders::order.pickup_lapses_note') }}@endif</p>
                @endif
            @elseif ($order->pickup->state === \Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState::HandedOver && $order->pickup->handedOverAt !== null)
                <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--ink-faint);">{{ trans('orders::order.pickup_handed_over_on', ['date' => $order->pickup->handedOverAt->format('Y-m-d H:i')]) }}</p>
            @endif
        @endif
    </div>
@endif

<div class="card card-pad">
    <p class="label-sm">{{ trans('orders::order.downloads') }}</p>
    @if (count($page->digitalGrants) > 0)
        <ul class="downloads-list">
            @foreach ($page->digitalGrants as $grant)
                <li>
                    <a href="{{ route('downloads.show', ['token' => $grant->accessToken]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        {{ $grant->fileLabel }}
                    </a>
                    <span class="fname">{{ $grant->originalFilename }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <div class="downloads-pending">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            {{ trans('orders::order.downloads_pending') }}
        </div>
    @endif
</div>
