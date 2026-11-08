<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Email\Contracts\EmailMessageCatalog;
use Litecommerce\Modules\Orders\Application\GrantDigitalAccessAfterLatePayment;
use Litecommerce\Modules\Orders\Application\ManageOrder;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Presentation\PickupFulfillmentPresenter;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStarter;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentReferenceAlreadyUsedException;
use Litecommerce\Modules\Returns\Contracts\ReturnCatalog;
use Litecommerce\Modules\Returns\Contracts\ReturnRequestData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

final class OrderController extends Controller
{
    public function index(Request $request, CurrencyRepository $currencies): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $query = Order::query()->orderByDesc('created_at');
        $total = (clone $query)->count();
        $places = collect($currencies->all())->mapWithKeys(fn ($currency): array => [$currency->code => $currency->decimalPlaces]);
        $rows = $query->forPage($page, $perPage)->get()->map(fn (Order $order): array => $this->summary($order, (int) $places->get($order->currency_code, 2)))->all();

        return Inertia::render('Panel/Orders/Index', [
            'orders' => new LengthAwarePaginator($rows, $total, $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]),
        ]);
    }

    public function show(string $order, PaymentAttemptCatalog $payments, OrderStatusRegistry $statuses, CurrencyRepository $currencies, DigitalAccessCatalog $digitalAccess, ReturnCatalog $returns, EmailMessageCatalog $emailMessages, PickupFulfillmentPresenter $fulfillment): Response|RedirectResponse
    {
        $model = Order::query()->with(['party', 'delivery', 'lines', 'statusHistory', 'notes', 'couponUsages'])->find($order);

        if ($model === null) {
            return $this->orderNotFound();
        }

        $currency = $currencies->find($model->currency_code);
        $decimalPlaces = $currency === null ? 2 : $currency->decimalPlaces;

        return Inertia::render('Panel/Orders/Show', [
            'order' => [
                ...$this->summary($model, $decimalPlaces),
                'locale' => $model->locale, 'source_type' => $model->source_type, 'source_id' => $model->source_id,
                'total_net_amount' => (string) $model->total_net_amount,
                'total_vat_amount' => (string) $model->total_vat_amount,
                'party' => $model->party?->only(['buyer_type', 'email', 'company_name', 'tax_identifier', 'registration_number', 'phone', 'billing_address', 'shipping_address']),
                'delivery' => $model->delivery?->only(['kind', 'name', 'net_amount', 'vat_amount', 'gross_amount', 'pickup_point_snapshot']),
                'fulfillment' => $fulfillment->present($model),
                'can_grant_digital_access' => GrantDigitalAccessAfterLatePayment::isEligible($model),
                'lines' => $model->lines->map(fn ($line): array => [
                    ...$line->only(['id', 'variant_id', 'product_type', 'sku', 'display_name', 'quantity', 'unit_amount', 'line_net_amount', 'line_vat_amount', 'line_gross_amount', 'stock_reservation_id']),
                    'coupon_allocations' => $line->allocations_snapshot['coupons'] ?? [],
                ])->all(),
                'coupon_usages' => $model->couponUsages->map(fn ($usage): array => $usage->only(['id', 'coupon_code', 'product_gross_amount', 'delivery_gross_amount']))->all(),
                'status_history' => $model->statusHistory->sortByDesc('occurred_at')->values()->map(fn ($entry): array => $entry->only(['old_status', 'new_status', 'actor_type', 'actor_id', 'reason', 'occurred_at']))->all(),
                'notes' => $model->notes->sortByDesc('created_at')->values()->map(fn ($note): array => $note->only(['id', 'body', 'author_type', 'author_id', 'created_at']))->all(),
                'customer_messages' => array_map(static fn ($message): array => [
                    'id' => $message->id,
                    'status' => $message->status,
                    'actor_id' => $message->actorId,
                    'created_at' => $message->createdAt?->format(DATE_ATOM),
                    'body' => $message->body,
                ], $emailMessages->forSource('order', $model->id, 'core.manual_customer_message')),
                'payment_attempts' => array_map(static fn ($attempt): array => [
                    'id' => $attempt->id, 'provider_id' => $attempt->providerId, 'method_id' => $attempt->methodId,
                    'transaction_id' => $attempt->providerTransactionId, 'status' => $attempt->status->value,
                    'amount' => $attempt->amount, 'currency_code' => $attempt->currencyCode,
                ], $payments->forOrder($model->id)),
                'available_statuses' => array_map(static fn ($status): array => ['value' => $status->key, 'label' => $status->label()], $statuses->all()),
                'digital_access' => array_map(fn (DigitalGrantData $grant): array => $this->digitalGrantData($grant), $digitalAccess->findForOrder($model->id)),
                'returns' => array_map(fn (ReturnRequestData $r): array => [
                    'id' => $r->id, 'status' => $r->status, 'created_at' => $r->createdAt->format(DATE_ATOM),
                    'pending_count' => count(array_filter($r->lines, fn ($line): bool => $line->status === 'pending')),
                    'approved_count' => count(array_filter($r->lines, fn ($line): bool => $line->status === 'approved')),
                ], $returns->findForOrder($model->id)),
            ],
        ]);
    }

    /** @return array{id:int,file_label:string,original_filename:string,granted_at:string,expires_at:?string,max_downloads:?int,downloads_used:int,revoked_at:?string,revoked_reason:?string,usable:bool} */
    private function digitalGrantData(DigitalGrantData $grant): array
    {
        return [
            'id' => $grant->id,
            'file_label' => $grant->fileLabel,
            'original_filename' => $grant->originalFilename,
            'granted_at' => $grant->grantedAt->format(DATE_ATOM),
            'expires_at' => $grant->expiresAt?->format(DATE_ATOM),
            'max_downloads' => $grant->maxDownloads,
            'downloads_used' => $grant->downloadsUsed,
            'revoked_at' => $grant->revokedAt?->format(DATE_ATOM),
            'revoked_reason' => $grant->revokedReason,
            'usable' => $grant->isUsable(),
        ];
    }

    public function extendDigitalAccess(Request $request, string $order, int $grant, DigitalAccessManager $manager, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['until' => ['nullable', 'date']]);
        $manager->extend($grant, isset($data['until']) ? new \DateTimeImmutable((string) $data['until']) : null);
        $this->auditGrant($request, $audit, $order, $grant, 'orders.digital_access.extended', ['until' => $data['until'] ?? null]);

        return back()->with('status', 'Termin dostępu cyfrowego został zaktualizowany.');
    }

    public function setDigitalDownloadLimit(Request $request, string $order, int $grant, DigitalAccessManager $manager, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['max_downloads' => ['nullable', 'integer', 'min:0']]);
        $manager->setDownloadLimit($grant, isset($data['max_downloads']) ? (int) $data['max_downloads'] : null);
        $this->auditGrant($request, $audit, $order, $grant, 'orders.digital_access.limit_updated', ['max_downloads' => $data['max_downloads'] ?? null]);

        return back()->with('status', 'Limit pobrań został zaktualizowany.');
    }

    public function revokeDigitalAccess(Request $request, string $order, int $grant, DigitalAccessManager $manager, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $manager->revoke($grant, (string) $data['reason']);
        $this->auditGrant($request, $audit, $order, $grant, 'orders.digital_access.revoked', ['reason' => $data['reason']]);

        return back()->with('status', 'Dostęp cyfrowy został cofnięty.');
    }

    /** @param array<string, mixed> $changes */
    private function auditGrant(Request $request, AuditLogger $audit, string $orderId, int $grantId, string $action, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'order',
            subjectId: $orderId,
            changes: ['grant_id' => $grantId, ...$changes],
        ));
    }

    public function confirmManualPayment(Request $request, string $order, PaymentConfirmation $payments): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:190'], 'paid_at' => ['nullable', 'date']]);
        try {
            $payments->confirmManual($order, (string) $data['reference'], new \DateTimeImmutable((string) ($data['paid_at'] ?? 'now')), (string) $request->user()->getAuthIdentifier());
        } catch (PaymentReferenceAlreadyUsedException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return back()->with('status', 'Pełna wpłata została zarejestrowana.');
    }

    public function cancel(Request $request, string $order, OrderPaymentManager $payments): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payments->cancelUnpaid($order, (string) $data['reason'], (string) $request->user()->getAuthIdentifier());

        return back()->with('status', 'Niezapłacone zamówienie zostało anulowane.');
    }

    public function retryPayment(Request $request, string $order, PaymentAttemptStarter $payments): RedirectResponse
    {
        $model = Order::query()->findOrFail($order);
        $payments->retry($model->id, $model->email);

        return back()->with('status', 'Utworzono nową próbę płatności.');
    }

    public function updateStatus(Request $request, string $order, ManageOrder $orders): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string', 'max:32'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $orders->changeStatus($order, (string) $data['status'], (string) $request->user()->getAuthIdentifier(), $data['reason'] ?? null);

        return back()->with('status', 'Status zamówienia został zmieniony.');
    }

    public function addNote(Request $request, string $order, ManageOrder $orders): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $orders->addNote($order, (string) $data['body'], (string) $request->user()->getAuthIdentifier());

        return back()->with('status', 'Notatka została dodana.');
    }

    public function sendCustomerMessage(Request $request, string $order, ManageOrder $orders): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $orders->sendCustomerMessage($order, (string) $data['body'], (string) $request->user()->getAuthIdentifier());

        return back()->with('status', 'Wiadomość do klienta została dodana do kolejki.');
    }

    private function orderNotFound(): RedirectResponse
    {
        return redirect()->route('panel.orders.index')->with('error', 'Zamówienie nie zostało znalezione.');
    }

    private function summary(Order $order, int $decimalPlaces): array
    {
        return [
            'id' => $order->id, 'email' => $order->email, 'mode' => $order->mode,
            'status' => $order->status, 'payment_status' => $order->payment_status,
            'payment_method_id' => $order->payment_method_id,
            'total_gross_amount' => (string) $order->total_gross_amount,
            'currency_code' => $order->currency_code,
            'currency_decimal_places' => $decimalPlaces,
            'payment_deadline_at' => $order->payment_deadline_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(), 'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
