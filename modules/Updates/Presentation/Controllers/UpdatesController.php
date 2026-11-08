<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Updates\Contracts\UpdateAlreadyRunningException;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Contracts\UpdateInstaller;
use Litecommerce\Modules\Updates\Contracts\UpdateOperationData;
use Litecommerce\Modules\Updates\Contracts\UpdateStatus;

final class UpdatesController extends Controller
{
    public function __construct(private readonly UpdateChecker $updates, private readonly UpdateInstaller $installer) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('updates', $this->present($this->updates->status(), $this->installer->current()));
    }

    public function check(): RedirectResponse
    {
        $status = $this->updates->checkNow();

        return $status->errorCode === null
            ? back()->with('status', trans($status->availableRelease === null ? 'updates::flash.up_to_date' : 'updates::flash.update_found'))
            : back()->with('error', trans('updates::errors.'.$status->errorCode));
    }

    public function setAutomatic(Request $request, AuditLogger $audit): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $this->updates->setAutomaticChecks((bool) $enabled);
        $this->audit($audit, $request, 'updates.automatic_checks_changed', ['enabled' => (bool) $enabled]);

        return back()->with('status', trans('updates::flash.automatic_saved'));
    }

    public function install(Request $request, AuditLogger $audit): RedirectResponse
    {
        $status = $this->updates->status();
        $release = $status->availableRelease;

        if ($release === null || $status->compatibility->problems !== []) {
            return back()->with('error', trans('updates::flash.not_installable'));
        }

        try {
            $this->installer->start($release, (string) $request->user()?->getAuthIdentifier());
        } catch (UpdateAlreadyRunningException) {
            return back()->with('error', trans('updates::flash.already_running'));
        }

        $this->audit($audit, $request, 'updates.started', ['from' => $status->currentVersion->toString(), 'to' => $release->version->toString()]);

        return back();
    }

    public function advance(): JsonResponse
    {
        @set_time_limit(0);

        return response()->json($this->presentOperation($this->installer->advance(max(1, (int) config('updates.step_budget_seconds')))));
    }

    /** @return array<string, mixed> */
    private function present(UpdateStatus $status, ?UpdateOperationData $operation): array
    {
        $release = $status->availableRelease;

        return [
            'current_version' => $status->currentVersion->toString(),
            'automatic_checks' => $status->automaticChecks,
            'checked_at' => $status->checkedAt,
            'error' => $status->errorCode === null ? null : trans('updates::errors.'.$status->errorCode),
            'release' => $release === null ? null : [
                'version' => $release->version->toString(),
                'notes_url' => $release->notesUrl,
                'published_at' => $release->publishedAt,
            ],
            'problems' => $status->compatibility->problems,
            'operation' => $operation?->status === 'completed' ? null : $this->presentOperation($operation),
        ];
    }

    /** @return array<string, mixed>|null */
    private function presentOperation(?UpdateOperationData $operation): ?array
    {
        if ($operation === null) {
            return null;
        }

        $steps = array_map(static fn (array $step): string => $step['status'], $operation->steps);
        $failedKey = array_search('failed', $steps, true);

        return [
            'id' => $operation->id,
            'target_version' => $operation->targetVersion,
            'status' => $operation->status,
            'steps' => $steps,
            'failed_step' => $failedKey === false ? null : $failedKey,
            'error' => $operation->errorCode === null ? null : $this->errorText($operation->errorCode),
            'detail' => $operation->errorMessage,
        ];
    }

    private function errorText(string $code): string
    {
        $key = 'updates::errors.'.$code;
        $text = trans($key);

        return $text === $key ? trans('updates::errors.unexpected_error') : $text;
    }

    /** @param array<string, mixed> $changes */
    private function audit(AuditLogger $audit, Request $request, string $action, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'updates',
            changes: $changes,
        ));
    }
}
