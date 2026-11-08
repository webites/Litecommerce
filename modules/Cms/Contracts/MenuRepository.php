<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** The only way outside Cms (Storefront's nav rendering) reads menu content — never the Eloquent model directly. */
interface MenuRepository
{
    /**
     * Resolves the menu assigned to $locationId (if any) into a ready-to-render
     * tree in $locale: a page/archetype item's label+url is looked up now
     * (auto-resolving from the underlying resource's own translation when no
     * label override was set); a custom item's url/label are used as stored;
     * a widget item comes back with url=null and widgetKey set, for Storefront
     * to finish resolving. Any item whose reference no longer exists, isn't
     * published, or has no translation in $locale is OMITTED from the
     * returned tree entirely (recursively) — never a dead link, the same
     * "log and skip" contract as every other registry in this codebase.
     *
     * @return ?list<MenuItemData> Null when no menu is assigned to $locationId.
     */
    public function resolveForLocation(string $locationId, string $locale): ?array;
}
