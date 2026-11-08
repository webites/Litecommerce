<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

/**
 * A static shell — the theme's own JS drives everything from here through
 * the existing JSON API (routes/storefront.php), per "Kontrakty widoków"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013): no
 * server-rendered cart DTO, to avoid a second copy of pricing logic that
 * must be recomputed live on every mutation anyway.
 */
final class CartPageController extends Controller
{
    public function show(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->render('cart.show', ['locale' => $locale]);
    }
}
