<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Resolves a theme-shipped default Puck document (raw JSON echoed by a Blade
 * view, e.g. theme::archetypes.category or theme::homepage) through the same
 * theme:: namespace fallback as blocks/partials — the active theme's own
 * copy, else default-storefront's. Falls back to $fallback (never an
 * exception) when the view is missing or fails to render/decode — the same
 * resilience contract as the rest of Cms\Infrastructure.
 */
final class ThemeDefaultContentResolver
{
    /**
     * @param  array{content: list<array{id?: string, type?: string, props?: array<string, mixed>}>}  $fallback
     * @return array{content: list<array{id?: string, type?: string, props?: array<string, mixed>}>}
     */
    public function resolve(string $view, array $fallback): array
    {
        if (View::exists($view)) {
            try {
                $decoded = json_decode(View::make($view)->render(), true);

                if (is_array($decoded) && is_array($decoded['content'] ?? null)) {
                    return $decoded;
                }
            } catch (Throwable $exception) {
                Log::warning("Theme default content [{$view}] failed to render.", ['exception' => $exception]);
            }
        }

        return $fallback;
    }
}
