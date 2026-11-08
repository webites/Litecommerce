<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;

final class MaintenanceController extends Controller
{
    public function enable(Request $request, MaintenanceMode $maintenance, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:'.(int) config('maintenance.message_max_length', 500)]]);
        $actorId = (string) $request->user()?->getAuthIdentifier();

        if (! $maintenance->enable(MaintenanceReason::Manual, $data['message'] ?? null, $actorId)) {
            return back()->with('error', trans('maintenance::panel.already_active'));
        }

        $this->record($audit, $actorId, 'maintenance.enabled');

        return back()->with('status', trans('maintenance::panel.enabled'));
    }

    public function disable(Request $request, MaintenanceMode $maintenance, AuditLogger $audit): RedirectResponse
    {
        if (! $maintenance->disable()) {
            return back()->with('error', trans('maintenance::panel.not_active'));
        }

        $this->record($audit, (string) $request->user()?->getAuthIdentifier(), 'maintenance.disabled');

        return back()->with('status', trans('maintenance::panel.disabled'));
    }

    private function record(AuditLogger $audit, string $actorId, string $action): void
    {
        $audit->record(new AuditEntryData(actorType: 'staff', actorId: $actorId, action: $action, subjectType: 'maintenance_mode'));
    }
}
