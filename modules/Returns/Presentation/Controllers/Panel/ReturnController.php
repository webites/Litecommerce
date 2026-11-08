<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Orders\Contracts\OrderLineDetailData;
use Litecommerce\Modules\Orders\Contracts\OrderReturnCatalog;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use Litecommerce\Modules\Returns\Application\RecordRefundPayout;
use Litecommerce\Modules\Returns\Contracts\ReturnCatalog;
use Litecommerce\Modules\Returns\Contracts\ReturnLineData;
use Litecommerce\Modules\Returns\Contracts\ReturnOperationException;
use Litecommerce\Modules\Returns\Contracts\ReturnReasonData;
use Litecommerce\Modules\Returns\Contracts\ReturnRequestData;
use Litecommerce\Modules\Returns\Domain\Decimal;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnLine;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnReason;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest as ReturnRequestModel;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

final class ReturnController extends Controller
{
    public function index(Request $request, ReturnCatalog $catalog): Response
    {
        $status = $request->query('status');
        $requests = ReturnRequestModel::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount(['lines as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderByDesc('created_at')->paginate(25);

        return Inertia::render('Panel/Returns/Index', [
            'returnRequests' => $requests->through(fn (ReturnRequestModel $row): array => [
                'id' => $row->id, 'order_id' => $row->order_id, 'email' => $row->email,
                'status' => $row->status, 'pending_count' => $row->pending_count,
                'created_at' => $row->created_at?->toIso8601String(),
            ]),
            'reasons' => array_map(fn (ReturnReasonData $r): array => ['id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'active' => $r->active], $catalog->allReasons()),
        ]);
    }

    public function show(string $returnRequest, ReturnCatalog $catalog, OrderReturnCatalog $orders, CurrencyRepository $currencies): Response|RedirectResponse
    {
        $data = $catalog->find($returnRequest);

        if ($data === null) {
            return $this->returnRequestNotFound();
        }

        $order = $orders->find($data->orderId);
        abort_if($order === null, 404);
        $linesByOrderLineId = collect($order->lines)->keyBy('id');
        $currency = $currencies->find($order->currencyCode);
        $decimalPlaces = $currency === null ? 2 : $currency->decimalPlaces;

        return Inertia::render('Panel/Returns/Show', [
            'returnRequest' => $this->requestData($data, $linesByOrderLineId),
            'order' => [
                'id' => $order->id, 'currency_code' => $order->currencyCode, 'currency_decimal_places' => $decimalPlaces,
                'payment_status' => $order->paymentStatus, 'email' => $order->email,
                'delivery_gross_amount' => $order->deliveryGrossAmount,
            ],
            'reasons' => array_map(fn (ReturnReasonData $r): array => ['id' => $r->id, 'name' => $r->name], $catalog->allReasons()),
        ]);
    }

    public function create(string $order, OrderReturnCatalog $orders, ReturnCatalog $catalog, CurrencyRepository $currencies): Response
    {
        $orderData = $orders->find($order);
        abort_if($orderData === null, 404);
        if ($orderData->paidAt === null) {
            abort(422, trans('returns::panel.order_not_paid'));
        }
        $currency = $currencies->find($orderData->currencyCode);
        $decimalPlaces = $currency === null ? 2 : $currency->decimalPlaces;

        return Inertia::render('Panel/Returns/Create', [
            'order' => [
                'id' => $orderData->id, 'currency_code' => $orderData->currencyCode,
                'currency_decimal_places' => $decimalPlaces, 'email' => $orderData->email,
            ],
            'lines' => array_values(array_map(fn (OrderLineDetailData $line): array => [
                'id' => $line->id, 'sku' => $line->sku, 'display_name' => $line->displayName,
                'product_type' => $line->productType, 'ordered_quantity' => $line->quantity,
                'remaining_quantity' => $this->remainingQuantity($line),
                'gross_amount' => $line->grossAmount,
            ], array_filter($orderData->lines, fn (OrderLineDetailData $line): bool => Decimal::compare($this->remainingQuantity($line), '0') > 0))),
            'reasons' => array_map(fn (ReturnReasonData $r): array => ['id' => $r->id, 'name' => $r->name], $catalog->activeReasons()),
        ]);
    }

    public function store(Request $request, string $order, CreateReturnRequest $creator, OrderReturnCatalog $orders, AuditLogger $audit): RedirectResponse
    {
        $orderData = $orders->find($order);
        abort_if($orderData === null, 404);

        /** @var array{comment:?string,lines:list<array{order_line_id:int,quantity:string,reason_id:?int,reason_comment:?string}>} $validated */
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
            'lines.*.reason_id' => ['nullable', 'integer', 'exists:return_reasons,id'],
            'lines.*.reason_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $returnRequest = $creator->execute($order, 'staff', $orderData->customerAccountId, $orderData->email, $validated['comment'] ?? null, $validated['lines']);
        } catch (ReturnOperationException $exception) {
            return back()->withErrors(['lines' => trans("returns::errors.{$exception->errorCode}", $exception->params)])->withInput();
        }

        $audit->record(new AuditEntryData(
            actorType: 'staff', actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'returns.request.created', subjectType: 'order', subjectId: $order,
            changes: ['return_request_id' => $returnRequest->id, 'lines' => count($validated['lines'])],
        ));

        return redirect()->route('panel.returns.show', $returnRequest->id)->with('status', trans('returns::panel.request_created'));
    }

    public function decide(Request $request, string $returnRequest, DecideReturnLines $decider, AuditLogger $audit): RedirectResponse
    {
        /** @var array{decisions:list<array{line_id:int,decision:string,restock?:bool,restocked_quantity?:?string,decision_reason?:?string}>,delivery_refund_gross_amount:?string} $validated */
        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.line_id' => ['required', 'integer'],
            'decisions.*.decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decisions.*.restock' => ['nullable', 'boolean'],
            'decisions.*.restocked_quantity' => ['nullable', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
            'decisions.*.decision_reason' => ['nullable', 'string', 'max:2000'],
            'delivery_refund_gross_amount' => ['nullable', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
        ]);

        try {
            $refund = $decider->execute(
                $returnRequest, $validated['decisions'], $validated['delivery_refund_gross_amount'] ?? null,
                (string) $request->user()?->getAuthIdentifier(),
            );
        } catch (ReturnOperationException $exception) {
            return back()->withErrors(['decisions' => trans("returns::errors.{$exception->errorCode}", $exception->params)]);
        }

        $audit->record(new AuditEntryData(
            actorType: 'staff', actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'returns.lines.decided', subjectType: 'return_request', subjectId: $returnRequest,
            changes: ['decisions' => $validated['decisions'], 'refund_record_id' => $refund?->id],
        ));

        return back()->with('status', trans('returns::panel.decisions_saved'));
    }

    public function payout(Request $request, string $returnRequest, int $refund, RecordRefundPayout $recorder, AuditLogger $audit): RedirectResponse
    {
        /** @var array{reference:string} $validated */
        $validated = $request->validate(['reference' => ['required', 'string', 'max:190']]);
        $recorder->execute($refund, $validated['reference'], (string) $request->user()?->getAuthIdentifier());

        $audit->record(new AuditEntryData(
            actorType: 'staff', actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'returns.refund.paid_out', subjectType: 'refund_record', subjectId: (string) $refund,
            changes: ['reference' => $validated['reference']],
        ));

        return back()->with('status', trans('returns::panel.payout_recorded'));
    }

    public function storeReason(Request $request, AuditLogger $audit): RedirectResponse
    {
        /** @var array{key:string,name:string} $validated */
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:return_reasons,key'],
            'name' => ['required', 'string', 'max:190'],
        ]);
        $position = 1 + (int) ReturnReason::query()->max('position');
        $reason = ReturnReason::query()->create([...$validated, 'active' => true, 'position' => $position]);

        $audit->record(new AuditEntryData(
            actorType: 'staff', actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'returns.reason.created', subjectType: 'return_reason', subjectId: (string) $reason->getKey(), changes: $validated,
        ));

        return back()->with('status', trans('returns::panel.reason_added'));
    }

    public function updateReason(Request $request, ReturnReason $reason, AuditLogger $audit): RedirectResponse
    {
        /** @var array{name:string,active:bool} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'active' => ['required', 'boolean'],
        ]);
        $reason->update($validated);

        $audit->record(new AuditEntryData(
            actorType: 'staff', actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'returns.reason.updated', subjectType: 'return_reason', subjectId: (string) $reason->getKey(), changes: $validated,
        ));

        return back()->with('status', trans('returns::panel.reason_saved'));
    }

    private function remainingQuantity(OrderLineDetailData $line): string
    {
        $alreadyApproved = ReturnLine::query()->where('order_line_id', $line->id)->where('status', 'approved')
            ->get()->reduce(fn (string $c, ReturnLine $l): string => Decimal::add($c, (string) $l->quantity), '0');

        return Decimal::roundHalfUp(Decimal::sub($line->quantity, $alreadyApproved), 6);
    }

    private function returnRequestNotFound(): RedirectResponse
    {
        return redirect()->route('panel.returns.index')->with('error', 'Zgłoszenie zwrotu nie zostało znalezione.');
    }

    /** @param  Collection<int, OrderLineDetailData>  $linesByOrderLineId */
    private function requestData(ReturnRequestData $data, $linesByOrderLineId): array
    {
        return [
            'id' => $data->id, 'order_id' => $data->orderId, 'source' => $data->source,
            'email' => $data->email, 'comment' => $data->comment, 'status' => $data->status,
            'created_at' => $data->createdAt->format(DATE_ATOM),
            'lines' => array_map(function (ReturnLineData $line) use ($linesByOrderLineId): array {
                $orderLine = $linesByOrderLineId->get($line->orderLineId);

                return [
                    'id' => $line->id, 'order_line_id' => $line->orderLineId,
                    'sku' => $orderLine?->sku, 'display_name' => $orderLine?->displayName,
                    'product_type' => $orderLine?->productType, 'ordered_quantity' => $orderLine?->quantity,
                    'quantity' => $line->quantity, 'reason_id' => $line->reasonId, 'reason_comment' => $line->reasonComment,
                    'status' => $line->status, 'restock' => $line->restock, 'restocked_quantity' => $line->restockedQuantity,
                    'net_amount' => $line->netAmount, 'vat_amount' => $line->vatAmount, 'gross_amount' => $line->grossAmount,
                    'coupon_allocations' => $line->couponAllocations, 'money_refund_amount' => $line->moneyRefundAmount,
                    'decision_reason' => $line->decisionReason, 'decided_by' => $line->decidedBy,
                    'decided_at' => $line->decidedAt?->format(DATE_ATOM),
                ];
            }, $data->lines),
            'refunds' => array_map(fn ($refund): array => [
                'id' => $refund->id, 'products_net_amount' => $refund->productsNetAmount,
                'products_vat_amount' => $refund->productsVatAmount, 'products_gross_amount' => $refund->productsGrossAmount,
                'coupon_restored_amount' => $refund->couponRestoredAmount,
                'delivery_gross_amount' => $refund->deliveryGrossAmount, 'money_refund_amount' => $refund->moneyRefundAmount,
                'decided_by' => $refund->decidedBy, 'decided_at' => $refund->decidedAt->format(DATE_ATOM),
                'paid_out_at' => $refund->paidOutAt?->format(DATE_ATOM), 'payout_reference' => $refund->payoutReference,
            ], $data->refunds),
        ];
    }
}
