{{-- $status/$paymentStatus are always the raw enum-value string, never the enum class itself (a theme has no PHP of its own). --}}
@php
    $statusTones = ['pending_payment' => 'warning', 'processing' => 'warning', 'payment_review' => 'warning', 'cancelled' => 'danger'];
    $paymentTones = ['unpaid' => 'neutral', 'paid' => 'success', 'late_payment' => 'warning', 'overpaid' => 'warning'];
    $statusLabel = \Illuminate\Support\Facades\Lang::has("orders::order.badge_status.{$status}") ? trans("orders::order.badge_status.{$status}") : $status;
    $paymentLabel = \Illuminate\Support\Facades\Lang::has("orders::order.order_payment_status.{$paymentStatus}") ? trans("orders::order.order_payment_status.{$paymentStatus}") : $paymentStatus;
    $statusTone = $statusTones[$status] ?? 'neutral';
    $paymentTone = $paymentTones[$paymentStatus] ?? 'neutral';
@endphp
<span class="badge badge-{{ $statusTone }}">{{ $statusLabel }}</span>
<span class="badge badge-{{ $paymentTone }}">{{ $paymentLabel }}</span>
