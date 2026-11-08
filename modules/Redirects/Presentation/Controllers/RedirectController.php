<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Redirects\Application\SaveRedirect;
use Litecommerce\Modules\Redirects\Application\SaveRedirectData;
use Litecommerce\Modules\Redirects\Infrastructure\Models\Redirect;
use Litecommerce\Modules\Redirects\Presentation\Requests\SaveRedirectRequest;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

final class RedirectController extends Controller
{
    public function __construct(private readonly LanguageRepository $languages) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('redirects', $this->settingsData($this->languages));
    }

    /** @return array<string, mixed> */
    private function settingsData(LanguageRepository $languages): array
    {
        return [
            'redirects' => Redirect::query()->orderByDesc('id')->get()->map(static fn (Redirect $redirect): array => [
                'id' => (int) $redirect->getKey(),
                'locale' => $redirect->locale,
                'from_path' => $redirect->from_path,
                'to_path' => $redirect->to_path,
            ])->all(),
            'languages' => array_map(static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
            ], array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled))),
        ];
    }

    public function store(SaveRedirectRequest $request, SaveRedirect $saver, AuditLogger $audit): RedirectResponse
    {
        $redirect = $saver->handle(null, $this->data($request));
        $this->audit($request, $audit, 'redirects.created', $redirect);

        return back()->with('status', trans('redirects::flash.added'));
    }

    public function update(SaveRedirectRequest $request, Redirect $redirect, SaveRedirect $saver, AuditLogger $audit): RedirectResponse
    {
        $saver->handle($redirect, $this->data($request));
        $this->audit($request, $audit, 'redirects.updated', $redirect);

        return back()->with('status', trans('redirects::flash.saved'));
    }

    public function destroy(Request $request, Redirect $redirect, AuditLogger $audit): RedirectResponse
    {
        $this->audit($request, $audit, 'redirects.deleted', $redirect);
        $redirect->delete();

        return back()->with('status', trans('redirects::flash.removed'));
    }

    private function data(SaveRedirectRequest $request): SaveRedirectData
    {
        return new SaveRedirectData(
            locale: (string) $request->validated('locale'),
            fromPath: (string) $request->validated('from_path'),
            toPath: (string) $request->validated('to_path'),
        );
    }

    private function audit(Request $request, AuditLogger $audit, string $action, Redirect $redirect): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'redirect',
            subjectId: (string) $redirect->getKey(),
            changes: ['locale' => $redirect->locale, 'from_path' => $redirect->from_path, 'to_path' => $redirect->to_path],
        ));
    }
}
