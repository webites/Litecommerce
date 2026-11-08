<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Extensions\Application\DisableExtension;
use Litecommerce\Modules\Extensions\Application\EnableExtension;
use Litecommerce\Modules\Extensions\Application\InstallExtension;
use Litecommerce\Modules\Extensions\Application\UninstallExtension;
use Litecommerce\Modules\Extensions\Application\UploadExtensionPackage;
use Litecommerce\Modules\Extensions\Contracts\ExtensionCatalog;
use Litecommerce\Modules\Extensions\Contracts\ExtensionData;
use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStateException;
use Litecommerce\Modules\Extensions\Infrastructure\EloquentExtensionSettingsStore;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\Models\ExtensionSetting;

final class ExtensionController extends Controller
{
    public function index(ExtensionCatalog $extensions): Response
    {
        return Inertia::render('Panel/Extensions/Index', [
            'extensions' => array_map(fn (ExtensionData $extension): array => $this->present($extension), $extensions->all()),
        ]);
    }

    public function show(string $extension, ExtensionCatalog $extensions): Response|RedirectResponse
    {
        $data = $extensions->find($extension);

        if ($data === null) {
            return $this->extensionNotFound();
        }

        $model = Extension::query()->find($extension);

        if ($model === null) {
            return $this->extensionNotFound();
        }

        $secretKeys = EloquentExtensionSettingsStore::secretKeys($model->manifest);
        $stored = ExtensionSetting::query()->where('extension_id', $extension)->pluck('value', 'key');
        $settingsSchema = array_map(
            static fn (array $field): array => in_array($field['key'], $secretKeys, true)
                ? [...$field, 'has_value' => ($stored[$field['key']] ?? '') !== '']
                : $field,
            $model->manifest['settings'] ?? [],
        );
        $values = $stored->except($secretKeys);

        return Inertia::render('Panel/Extensions/Show', [
            'extension' => $this->present($data),
            'settings_schema' => $settingsSchema,
            'settings_values' => $values,
        ]);
    }

    public function store(Request $request, UploadExtensionPackage $uploader, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(
            ['package' => ['required', 'file', 'extensions:zip'], 'acknowledge_untrusted' => ['accepted']],
            ['acknowledge_untrusted.accepted' => trans('extensions::errors.untrusted_not_acknowledged')],
        );

        try {
            $extension = $uploader->execute($data['package']->getRealPath());
        } catch (ExtensionInstallationException $exception) {
            return back()->withErrors(['package' => trans("extensions::errors.{$exception->errorCode}", $exception->params)]);
        }

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'extension.package.uploaded',
            subjectType: 'extension',
            subjectId: $extension->id,
            metadata: ['version' => $extension->version, 'package_sha256' => $extension->package_sha256],
        ));

        return back()->with('status', trans('extensions::flash.package_uploaded'));
    }

    public function install(string $extension, InstallExtension $action): RedirectResponse
    {
        try {
            $action->execute($extension);
        } catch (ExtensionInstallationException $exception) {
            return back()->withErrors(['extension' => trans("extensions::errors.{$exception->errorCode}", $exception->params)]);
        }

        return back()->with('status', trans('extensions::flash.installed'));
    }

    public function enable(string $extension, EnableExtension $action): RedirectResponse
    {
        try {
            $action->execute($extension);
        } catch (ExtensionStateException $exception) {
            return back()->withErrors(['extension' => $exception->getMessage()]);
        }

        return back()->with('status', trans('extensions::flash.enabled'));
    }

    public function disable(string $extension, DisableExtension $action): RedirectResponse
    {
        try {
            $action->execute($extension);
        } catch (ExtensionStateException $exception) {
            return back()->withErrors(['extension' => $exception->getMessage()]);
        }

        return back()->with('status', trans('extensions::flash.disabled'));
    }

    public function destroy(string $extension, UninstallExtension $action): RedirectResponse
    {
        try {
            $action->execute($extension);
        } catch (ExtensionStateException $exception) {
            return back()->withErrors(['extension' => $exception->getMessage()]);
        }

        return redirect()->route('panel.extensions.index')->with('status', trans('extensions::flash.uninstalled'));
    }

    public function updateSettings(Request $request, string $extension): RedirectResponse
    {
        $model = Extension::query()->findOrFail($extension);
        $schema = $model->manifest['settings'] ?? [];
        $allowedKeys = array_map(static fn (array $field): string => (string) $field['key'], $schema);

        $data = $request->validate(['values' => ['present', 'array']]);

        $secretKeys = EloquentExtensionSettingsStore::secretKeys($model->manifest);

        foreach ($data['values'] as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }
            $stored = is_scalar($value) ? (string) $value : json_encode($value);
            if (in_array($key, $secretKeys, true)) {
                if ($value === null || $value === '') {
                    continue;
                }
                $stored = Crypt::encryptString($stored);
            }
            ExtensionSetting::query()->updateOrCreate(
                ['extension_id' => $extension, 'key' => $key],
                ['value' => $stored],
            );
        }

        return back()->with('status', trans('extensions::flash.settings_saved'));
    }

    private function extensionNotFound(): RedirectResponse
    {
        return redirect()->route('panel.extensions.index')->with('error', trans('extensions::flash.not_found'));
    }

    /** @return array<string, mixed> */
    private function present(ExtensionData $extension): array
    {
        return [
            'id' => $extension->id,
            'name' => $extension->name,
            'version' => $extension->version,
            'type' => $extension->type,
            'status' => $extension->status->value,
            'dependency_ids' => $extension->dependencyIds,
            'installed_at' => $extension->installedAt?->format(DATE_ATOM),
            'enabled_at' => $extension->enabledAt?->format(DATE_ATOM),
            'package_sha256' => $extension->packageSha256,
        ];
    }
}
