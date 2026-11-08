<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Lets a plugin (which has boot(), unlike a theme — see
 * Themes\Contracts\ActiveThemeResolver::menuLocations() for the theme-side
 * declaration, a manifest field rather than a runtime call) declare an
 * additional menu location. A plugin-declared location only ever renders if
 * some active theme's own layout chooses to include the nav-menu partial
 * for this location's id at the right spot — a plugin cannot force a
 * theme's Blade to include anything, the same opt-in relationship a theme
 * already has with the storefrontSlot Blade directive.
 */
interface MenuLocationRegistry
{
    public function register(string $id, string $extensionId, string $label): void;

    /** @return list<array{id: string, extensionId: string, label: string}> */
    public function all(): array;
}
