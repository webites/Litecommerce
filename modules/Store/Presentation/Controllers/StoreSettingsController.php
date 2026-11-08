<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;
use Litecommerce\Modules\DemoData\Contracts\DemoDataBatchData;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Store\Application\SanitizeStoredHtml;
use Litecommerce\Modules\Store\Application\UpdateStoreSettings;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class StoreSettingsController extends Controller
{
    public function __construct(
        private readonly StoreSettingsReader $settings,
        private readonly DemoDataManager $demoData,
        private readonly CacheMaintenance $cache,
        private readonly HtmlContentFilter $htmlFilter,
        private readonly MaintenanceMode $maintenance,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Panel/Store/Settings/Index');
    }

    public function generalTab(SettingsTabContext $context): SettingsTabContent
    {
        $current = $this->settings->current();

        return SettingsTabContent::component('general', [
            'settings' => [
                'store_name' => $current->storeName,
                'logo_url' => $current->logoUrl,
                'blog_enabled' => $current->blogEnabled,
                'products_per_page' => $current->catalogProductsPerPage,
            ],
        ]);
    }

    public function advancedTab(SettingsTabContext $context): SettingsTabContent
    {
        $cache = $this->cache->status();

        return SettingsTabContent::component('advanced', [
            'htmlFilter' => ['enabled' => $this->htmlFilter->enabled()],
            'maintenance' => $this->presentMaintenance(),
            'demoData' => $this->presentDemoData($this->demoData->active()),
            'cache' => [
                'store' => $cache->store,
                'available' => $cache->available,
                'supports_locks' => $cache->supportsLocks,
                'supports_tags' => $cache->supportsTags,
            ],
        ]);
    }

    public function updateHtmlFilter(Request $request, StoreSettingsStore $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['filter_html' => ['required', 'boolean']]);
        $settings->set(StoreSettingKeys::FilterHtml, (bool) $data['filter_html']);
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'store.html_filter.updated',
            subjectType: 'store_settings',
            metadata: ['enabled' => (bool) $data['filter_html']],
        ));

        return back()->with('status', trans('store::panel.saved'));
    }

    public function sanitizeStoredHtml(Request $request, SanitizeStoredHtml $sanitize, AuditLogger $audit): RedirectResponse
    {
        $result = $sanitize->handle();
        $total = array_sum($result);
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'store.html_filter.stored_content_sanitized',
            subjectType: 'store_settings',
            metadata: ['changed' => $total],
        ));

        return back()->with('status', trans('store::panel.html_filter_sanitized', ['count' => $total]));
    }

    public function update(Request $request, UpdateStoreSettings $update): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'blog_enabled' => ['required', 'boolean'],
            'products_per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $update->basics($data['store_name'], $data['blog_enabled'], (int) $data['products_per_page']);

        return back()->with('status', trans('store::panel.saved'));
    }

    public function uploadLogo(Request $request, UpdateStoreSettings $update): RedirectResponse
    {
        $data = $request->validate(['logo' => ['required', 'image', 'max:5120']]);
        $file = $data['logo'];
        $contents = file_get_contents($file->getRealPath());
        abort_if($contents === false, 422);

        $update->replaceLogo(
            $contents,
            $file->getClientOriginalName(),
            $file->getMimeType() ?: 'application/octet-stream',
            (string) $request->user()->getAuthIdentifier(),
        );

        return back()->with('status', trans('store::panel.logo_saved'));
    }

    public function removeLogo(UpdateStoreSettings $update): RedirectResponse
    {
        $update->removeLogo();

        return back()->with('status', trans('store::panel.logo_removed'));
    }

    /** @return array<string, mixed> */
    private function presentDemoData(?DemoDataBatchData $batch): array
    {
        return $batch === null ? [
            'active' => false,
            'status' => null,
            'resource_count' => 0,
            'locale' => null,
        ] : [
            'active' => true,
            'status' => $batch->status,
            'resource_count' => $batch->resourceCount,
            'locale' => $batch->locale,
        ];
    }

    /** @return array{active: bool, reason: ?string, message: ?string} */
    private function presentMaintenance(): array
    {
        $state = $this->maintenance->state();

        return ['active' => $state !== null, 'reason' => $state?->reason->value, 'message' => $state?->message];
    }
}
