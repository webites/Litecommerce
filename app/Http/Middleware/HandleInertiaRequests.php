<?php

namespace App\Http\Middleware;

use App\Support\I18n\PanelTranslations;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $staffId = (int) $user?->getAuthIdentifier();

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'translations' => fn (): array => app(PanelTranslations::class)->forCurrentLocale(),
            'maintenance' => fn (): ?array => ($state = app(MaintenanceMode::class)->state()) === null ? null : ['reason' => $state->reason->value, 'message' => $state->message],
            'settingsTabs' => fn (): array => app(SettingsTabRegistry::class)->visible(),
            'flash' => [
                'status' => fn (): mixed => $request->session()->get('status'),
                // Danger-toned counterpart of 'status' (rendered via <Alert tone="danger">)
                // — e.g. redirecting to a listing after an edit/update/delete targeted an
                // id that no longer exists, see litecommerce-panel-ui skill's "Zasada 7".
                'error' => fn (): mixed => $request->session()->get('error'),
                // Set by Themes\Presentation\Controllers\Panel\ThemeController::activate()
                // right after a successful activation — Themes/Show.jsx shows the
                // "set this theme's default homepage/archive templates?" modal when true.
                'promptHomepageReset' => fn (): mixed => $request->session()->get('promptHomepageReset'),
                // Set by Cms\PageController::save() / Blog's PostController::update() /
                // CategoryController::update() when an already-published resource's slug
                // just changed — {locale, from_path, to_path} or null. RedirectSuggestionModal
                // (resources/js/Components/panel/RedirectSuggestionModal.jsx) offers to create
                // the 301 rather than doing it silently — same "ask, never auto-create" spec
                // as docs/knowledge-base/09-presentation.md's redirects section.
                'redirectSuggestion' => fn (): mixed => $request->session()->get('redirectSuggestion'),
            ],
            'auth' => [
                'user' => ! $user instanceof StaffAccount ? null : [
                    'id' => $staffId,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'can' => [
                    'manageStaff' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageStaff->value),
                    'manageStoreSettings' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageStoreSettings->value),
                    'manageEmail' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageEmail->value),
                    'manageCatalog' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageCatalog->value),
                    'managePricing' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManagePricing->value),
                    'manageInventory' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageInventory->value),
                    'manageDelivery' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageDelivery->value),
                    'manageCoupons' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageCoupons->value),
                    'viewOrders' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ViewOrders->value),
                    'manageOrders' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageOrders->value),
                    'manageRefunds' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageRefunds->value),
                    'manageExtensions' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageExtensions->value),
                    'manageThemes' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageThemes->value),
                    'manageContent' => $staffId > 0
                        && app(StaffPermissionChecker::class)->allows($staffId, CorePermission::ManageContent->value),
                    'blogEnabled' => app(StoreSettingsReader::class)->current()->blogEnabled,
                ],
            ],
        ];
    }
}
