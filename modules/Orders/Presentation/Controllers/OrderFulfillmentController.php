<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Orders\Contracts\InvalidFulfillmentTransitionException;
use Litecommerce\Modules\Orders\Contracts\OrderFulfillmentManager;
use Litecommerce\Modules\Payments\Contracts\PaymentReferenceAlreadyUsedException;

final class OrderFulfillmentController extends Controller
{
    public function markReady(Request $request, string $order, OrderFulfillmentManager $fulfillment, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['pickup_deadline_at' => ['nullable', 'date']]);
        $deadline = isset($data['pickup_deadline_at']) ? new \DateTimeImmutable((string) $data['pickup_deadline_at']) : null;
        $actor = (string) $request->user()?->getAuthIdentifier();

        return $this->run(fn (): bool => $fulfillment->markReadyForPickup($order, $actor, $deadline), $audit, $actor, $order, 'orders.fulfillment.ready_for_pickup', ['pickup_deadline_at' => $data['pickup_deadline_at'] ?? null], 'Zamówienie oznaczono jako gotowe do odbioru, klient dostał e-mail.');
    }

    public function handOver(Request $request, string $order, OrderFulfillmentManager $fulfillment, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['payment_reference' => ['nullable', 'string', 'max:190'], 'paid_at' => ['nullable', 'date']]);
        $reference = isset($data['payment_reference']) && $data['payment_reference'] !== '' ? (string) $data['payment_reference'] : null;
        $paidAt = isset($data['paid_at']) ? new \DateTimeImmutable((string) $data['paid_at']) : null;
        $actor = (string) $request->user()?->getAuthIdentifier();

        return $this->run(fn (): bool => $fulfillment->confirmHandover($order, $actor, $reference, $paidAt), $audit, $actor, $order, 'orders.fulfillment.handed_over', ['payment_reference' => $reference], 'Wydanie towaru zostało zapisane.');
    }

    /** @param array<string, mixed> $changes */
    private function run(\Closure $transition, AuditLogger $audit, string $actor, string $orderId, string $action, array $changes, string $status): RedirectResponse
    {
        try {
            $changed = $transition();
        } catch (InvalidFulfillmentTransitionException $e) {
            return back()->withErrors(['fulfillment' => $e->getMessage()]);
        } catch (PaymentReferenceAlreadyUsedException $e) {
            return back()->withErrors(['payment_reference' => $e->getMessage()]);
        }

        if ($changed) {
            $audit->record(new AuditEntryData(actorType: 'staff', actorId: $actor, action: $action, subjectType: 'order', subjectId: $orderId, changes: $changes));
        }

        return back()->with('status', $status);
    }
}
