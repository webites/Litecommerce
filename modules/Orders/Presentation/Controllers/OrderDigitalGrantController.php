<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Orders\Contracts\DigitalGrantRefusedException;
use Litecommerce\Modules\Orders\Contracts\OrderDigitalAccessGranting;

final class OrderDigitalGrantController extends Controller
{
    public function grantAfterLatePayment(Request $request, string $order, OrderDigitalAccessGranting $granting, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $actor = (string) $request->user()?->getAuthIdentifier();

        try {
            $created = $granting->grantAfterLatePayment($order, $actor, (string) $data['reason']);
        } catch (DigitalGrantRefusedException $e) {
            return back()->withErrors(['digital_grant' => $e->getMessage()]);
        }

        if ($created > 0) {
            $audit->record(new AuditEntryData(
                actorType: 'staff', actorId: $actor, action: 'orders.digital_access.granted_after_late_payment',
                subjectType: 'order', subjectId: $order, changes: ['reason' => $data['reason'], 'grants_created' => $created],
            ));
        }

        return back()->with('status', trans('orders::flash.digital_access_granted'));
    }
}
