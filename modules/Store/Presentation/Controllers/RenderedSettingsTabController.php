<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class RenderedSettingsTabController extends Controller
{
    public function show(string $tab, Request $request, SettingsTabRegistry $tabs): JsonResponse
    {
        $visibleTab = collect($tabs->visible())->firstWhere('id', $tab);
        abort_if($visibleTab === null, 404);

        $content = $tabs->render($tab, new SettingsTabContext(
            (int) $request->user()->getAuthIdentifier(),
            app()->getLocale(),
        ));
        abort_if($content === null, 404);

        return response()->json([
            'id' => $tab,
            'label' => $visibleTab['label'],
            'component' => $content->component,
            'props' => $content->props,
            'html' => $content->html,
        ]);
    }
}
