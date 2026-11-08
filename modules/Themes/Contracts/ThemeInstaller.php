<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

/**
 * Upload + activate in one call — used by the installer to seed the
 * bundled default theme from a ZIP it builds itself out of
 * resources/themes/default-storefront/, going through the exact same
 * pipeline a real ZIP upload through the panel would (see "Domyślny motyw
 * seedowany automatycznie", docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013)
 * — no parallel "built-in theme" code path with different rules.
 */
interface ThemeInstaller
{
    /** @throws ThemeInstallationException|ThemeStateException same failure modes as the individual upload/activate steps */
    public function installAndActivate(string $zipPath): void;
}
