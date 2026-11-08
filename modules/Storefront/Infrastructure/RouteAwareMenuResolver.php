<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Http\Request;
use Litecommerce\Modules\Cms\Contracts\MenuItemData;
use Litecommerce\Modules\Cms\Contracts\MenuItemType;
use Litecommerce\Modules\Cms\Contracts\MenuRepository;
use Litecommerce\Modules\Storefront\Contracts\MenuNodeViewData;
use Litecommerce\Modules\Storefront\Contracts\MenuRenderer;
use Litecommerce\Modules\Storefront\Contracts\MenuWidgetRegistry;

/**
 * Bound as a singleton, memoizes its result per (Request, locationId) via a
 * WeakMap keyed by the Request instance itself — same reasoning and same
 * leak-free lifecycle as RouteAwareLanguageAlternateResolver: the theme::*
 * view composer calls this once per view render (layout + every block/
 * partial in one request), but the answer can never change mid-request.
 *
 * A location with no menu assigned renders NOTHING (see MenuRenderer's own
 * docblock) — the merchant configures the whole menu in the panel, on
 * purpose there is no code-level default that guesses at "products, cart,
 * a language switcher" or any other business content on their behalf.
 *
 * A widget's own render() closure typically renders a theme:: view (e.g. the
 * 'cart' widget renders theme::partials.cart-link) — and rendering ANY
 * theme:: view re-triggers the SAME view composer that called this method in
 * the first place, which calls back into render() for the SAME location
 * before the outer call has finished. Without the placeholder written
 * before resolving below, that would recurse indefinitely whenever a
 * location's own tree includes a widget item. The placeholder makes the
 * re-entrant call see "nothing resolved yet" and return immediately instead
 * of recursing — harmless, since that re-entrant call's own $navMenus is
 * only used by the widget's OWN partial, none of which include
 * nav-menu.blade.php.
 */
final class RouteAwareMenuResolver implements MenuRenderer
{
    /** @var \WeakMap<Request, array<string, list<MenuNodeViewData>>> */
    private \WeakMap $cache;

    public function __construct(
        private readonly MenuRepository $menus,
        private readonly MenuWidgetRegistry $widgets,
    ) {
        $this->cache = new \WeakMap;
    }

    public function render(Request $request, string $locationId, string $locale): array
    {
        $forRequest = $this->cache[$request] ?? [];

        if (array_key_exists($locationId, $forRequest)) {
            return $forRequest[$locationId];
        }

        // Recursion guard — see class docblock. Written BEFORE resolving.
        $forRequest[$locationId] = [];
        $this->cache[$request] = $forRequest;

        $items = $this->menus->resolveForLocation($locationId, $locale);
        $result = $items === null ? [] : $this->toViewData($items);

        $forRequest = $this->cache[$request];
        $forRequest[$locationId] = $result;
        $this->cache[$request] = $forRequest;

        return $result;
    }

    /**
     * @param  list<MenuItemData>  $items
     * @return list<MenuNodeViewData>
     */
    private function toViewData(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $node = $this->toNode($item);

            if ($node !== null) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function toNode(MenuItemData $item): ?MenuNodeViewData
    {
        $children = $this->toViewData($item->children);
        $cssClasses = $children === [] ? $item->cssClasses : [...$item->cssClasses, 'sf-menu-item-has-children'];

        if ($item->type === MenuItemType::Widget) {
            $html = $item->widgetKey === null ? null : $this->widgets->render($item->widgetKey, $item->label);

            return $html === null ? null : new MenuNodeViewData(label: $item->label ?? '', url: null, html: $html, targetBlank: false, children: $children, cssClasses: $cssClasses);
        }

        if ($item->url === null) {
            return null;
        }

        return new MenuNodeViewData(label: $item->label ?? '', url: $item->url, html: null, targetBlank: $item->targetBlank, children: $children, cssClasses: $cssClasses);
    }
}
