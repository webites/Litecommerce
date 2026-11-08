<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * No theme is active, or the active theme has no Blade TOP-LEVEL view for
 * the requested name — either way the storefront cannot render this page.
 * Core top-level views have no fallback. Optional built-in module pages may
 * explicitly inherit their default-storefront implementation, but still throw
 * this exception when no theme is active or no implementation exists; see
 * Contracts\ThemeRenderer and "Zakres i świadome cięcia"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 *
 * `render()` is Laravel's own hook (Illuminate\Foundation\Exceptions\Handler
 * calls it automatically when present) — no separate registration in
 * bootstrap/app.php needed for this single, plain-message 503.
 */
final class ThemeNotActiveException extends \RuntimeException
{
    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $this->getMessage()], 503);
        }

        return new Response($this->getMessage(), 503);
    }
}
