<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;

final class ClearApplicationCacheController extends Controller
{
    public function __invoke(Request $request, CacheMaintenance $cache, AuditLogger $audit): RedirectResponse
    {
        $cache->clearApplicationCache();

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'cache.application.cleared',
            subjectType: 'application_cache',
        ));

        return back()->with('status', trans('cache::panel.cleared'));
    }
}
