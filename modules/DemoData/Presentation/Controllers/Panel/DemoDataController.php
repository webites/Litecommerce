<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;

final class DemoDataController extends Controller
{
    public function store(DemoDataManager $demoData): RedirectResponse
    {
        try {
            $batch = $demoData->install();
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', trans('demo-data::panel.installed', ['count' => $batch->resourceCount]));
    }

    public function destroy(DemoDataManager $demoData): RedirectResponse
    {
        try {
            $batch = $demoData->remove();
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', trans('demo-data::panel.removed', ['count' => $batch->resourceCount]));
    }
}
