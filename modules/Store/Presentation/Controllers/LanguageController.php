<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Store\Application\CreateLanguage;
use Litecommerce\Modules\Store\Application\UpdateLanguage;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;

final class LanguageController extends Controller
{
    public function __construct(private readonly LanguageRepository $languages) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('languages', [
            'languages' => array_map(static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
                'is_default' => $language->isDefault,
                'is_enabled' => $language->isEnabled,
                'noindex' => $language->noindex,
            ], $this->languages->all()),
        ]);
    }

    public function store(Request $request, CreateLanguage $creator, AuditLogger $audit): RedirectResponse
    {
        /** @var array{code:string,name:string,is_enabled:bool,noindex:bool} $data */
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,10}$/', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'noindex' => ['required', 'boolean'],
        ]);

        $language = $creator->handle($data['code'], $data['name'], $data['is_enabled'], $data['noindex']);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'store.language.added',
            subjectType: 'language',
            subjectId: (string) $language->getKey(),
            changes: ['code' => $data['code']],
        ));

        return back()->with('status', trans('store::panel.language_added', ['name' => $data['name']]));
    }

    public function update(Request $request, Language $language, UpdateLanguage $updater, AuditLogger $audit): RedirectResponse
    {
        /** @var array{name:string,is_enabled:bool,is_default:bool,noindex:bool} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'noindex' => ['required', 'boolean'],
        ]);

        $updater->handle($language, $data['name'], $data['is_enabled'], $data['is_default'], $data['noindex']);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'store.language.updated',
            subjectType: 'language',
            subjectId: (string) $language->getKey(),
            changes: $data,
        ));

        return back()->with('status', trans('store::panel.language_saved', ['code' => $language->code]));
    }
}
