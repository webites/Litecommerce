<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** One already-resolved menu node, ready to render — Storefront never sees the raw stored tree, only this. */
final readonly class MenuItemData
{
    /**
     * @param  list<MenuItemData>  $children
     * @param  list<string>  $cssClasses  A theme/plugin CSS hook, e.g. ['sf-menu-item', 'sf-menu-item-widget', 'sf-menu-item-widget-cart'] — the WordPress-style "what is this item" class list, so a theme can target one specific item (or every item of one kind) without editing PHP. 'sf-menu-item-has-children' is appended by Storefront once children are finalized for display, not here.
     */
    public function __construct(
        public MenuItemType $type,
        /** Already-resolved display text (label override, or auto-resolved from the underlying page/archetype instance in the request's locale). Null only for a widget item with no label override — the widget's own render() decides its own text, if any. */
        public ?string $label,
        /** Resolved URL for page/archetype/custom; always null for widget — Storefront resolves what a widget renders, not Cms. */
        public ?string $url,
        /** Set only when type === Widget. */
        public ?string $widgetKey,
        public bool $targetBlank,
        public array $children,
        public array $cssClasses,
    ) {}
}
