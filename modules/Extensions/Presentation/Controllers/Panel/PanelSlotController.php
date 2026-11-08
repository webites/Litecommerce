<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Extensions\Contracts\PanelSlotRegistry;

/**
 * The panel fetches a named slot's plugin-rendered fragments over this
 * endpoint and mounts them as sanitized HTML — see PanelSlotRegistry and
 * "Rozruch i rozszerzalność" (08-extensions.md#projekt-techniczny-p1-012).
 * Query params (e.g. ?order_id=... for a per-order slot like
 * "orders.show.documents") are passed through verbatim as render context.
 */
final class PanelSlotController extends Controller
{
    public function show(string $slot, Request $request, PanelSlotRegistry $registry): JsonResponse
    {
        /** @var array<string, string> $context */
        $context = $request->query();

        return response()->json(['fragments' => $registry->render($slot, $context)]);
    }
}
