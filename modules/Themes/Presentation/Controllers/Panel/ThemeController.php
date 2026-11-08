<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Themes\Application\ActivateTheme;
use Litecommerce\Modules\Themes\Application\DeactivateTheme;
use Litecommerce\Modules\Themes\Application\DeleteTheme;
use Litecommerce\Modules\Themes\Application\UploadThemePackage;
use Litecommerce\Modules\Themes\Contracts\ThemeCatalog;
use Litecommerce\Modules\Themes\Contracts\ThemeData;
use Litecommerce\Modules\Themes\Contracts\ThemeInstallationException;
use Litecommerce\Modules\Themes\Contracts\ThemeStateException;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;
use Litecommerce\Modules\Themes\Infrastructure\Models\ThemeSetting;

final class ThemeController extends Controller
{
    public function index(ThemeCatalog $themes): Response
    {
        return Inertia::render('Panel/Themes/Index', [
            'themes' => array_map(fn (ThemeData $theme): array => $this->present($theme), $themes->all()),
        ]);
    }

    public function show(string $theme, ThemeCatalog $themes): Response|RedirectResponse
    {
        $data = $themes->find($theme);

        if ($data === null) {
            return $this->themeNotFound();
        }

        $model = Theme::query()->find($theme);

        if ($model === null) {
            return $this->themeNotFound();
        }

        $settingsSchema = $model->manifest['settings'] ?? [];
        $values = ThemeSetting::query()->where('theme_id', $theme)->pluck('value', 'key');

        return Inertia::render('Panel/Themes/Show', [
            'theme' => $this->present($data),
            'settings_schema' => $settingsSchema,
            'settings_values' => $values,
        ]);
    }

    public function store(Request $request, UploadThemePackage $uploader, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(
            ['package' => ['required', 'file', 'extensions:zip'], 'acknowledge_untrusted' => ['accepted']],
            ['acknowledge_untrusted.accepted' => trans('themes::errors.untrusted_not_acknowledged')],
        );

        try {
            $theme = $uploader->execute($data['package']->getRealPath());
        } catch (ThemeInstallationException $exception) {
            return back()->withErrors(['package' => trans("themes::errors.{$exception->errorCode}", $exception->params)]);
        }

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'theme.package.uploaded',
            subjectType: 'theme',
            subjectId: $theme->id,
            metadata: ['version' => $theme->version, 'package_sha256' => $theme->package_sha256],
        ));

        return back()->with('status', trans('themes::panel.package_uploaded'));
    }

    public function activate(string $theme, ActivateTheme $action): RedirectResponse
    {
        try {
            $action->execute($theme);
        } catch (ThemeStateException $exception) {
            return back()->withErrors(['theme' => trans("themes::errors.{$exception->errorCode}", $exception->params)]);
        }

        return back()->with('status', trans('themes::panel.activated'))->with('promptHomepageReset', true);
    }

    public function deactivate(string $theme, DeactivateTheme $action): RedirectResponse
    {
        $action->execute($theme);

        return back()->with('status', trans('themes::panel.deactivated'));
    }

    public function destroy(string $theme, DeleteTheme $action): RedirectResponse
    {
        try {
            $action->execute($theme);
        } catch (ThemeStateException $exception) {
            return back()->withErrors(['theme' => trans("themes::errors.{$exception->errorCode}", $exception->params)]);
        }

        return redirect()->route('panel.themes.index')->with('status', trans('themes::panel.deleted'));
    }

    public function updateSettings(Request $request, string $theme): RedirectResponse
    {
        $model = Theme::query()->findOrFail($theme);
        $schema = $model->manifest['settings'] ?? [];
        $allowedKeys = array_map(static fn (array $field): string => (string) $field['key'], $schema);

        $data = $request->validate(['values' => ['present', 'array']]);

        foreach ($data['values'] as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }
            ThemeSetting::query()->updateOrCreate(
                ['theme_id' => $theme, 'key' => $key],
                ['value' => is_scalar($value) ? (string) $value : json_encode($value)],
            );
        }

        return back()->with('status', trans('themes::panel.settings_saved'));
    }

    private function themeNotFound(): RedirectResponse
    {
        return redirect()->route('panel.themes.index')->with('error', 'Motyw nie został znaleziony.');
    }

    /** @return array<string, mixed> */
    private function present(ThemeData $theme): array
    {
        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'version' => $theme->version,
            'engine' => $theme->engine,
            'status' => $theme->status->value,
            'required_plugin_ids' => $theme->requiredPluginIds,
            'installed_at' => $theme->installedAt?->format(DATE_ATOM),
            'package_sha256' => $theme->packageSha256,
            'activated_at' => $theme->activatedAt?->format(DATE_ATOM),
        ];
    }
}
