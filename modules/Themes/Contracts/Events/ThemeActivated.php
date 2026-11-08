<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a theme's activation
 * transaction commits — the generic hook a theme or plugin listens to from
 * its own boot(): `Event::listen(ThemeActivated::class, ...)`. Carries only
 * the theme id — a listener that needs more reads it back through
 * Themes\Contracts\ThemeCatalog, never through this module's Eloquent models.
 * Mirrors Orders\Contracts\Events\OrderPlaced's contract exactly.
 */
final readonly class ThemeActivated
{
    public function __construct(public string $themeId) {}
}
