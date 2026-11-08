<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/** One fully-resolved, ready-to-render menu node — a link (label+url) or a widget (html), never both. */
final readonly class MenuNodeViewData
{
    /**
     * @param  list<MenuNodeViewData>  $children
     * @param  list<string>  $cssClasses  Carried over from Cms\Contracts\MenuItemData::$cssClasses, plus 'sf-menu-item-has-children' appended here once $children is final — a theme's own CSS can target any item by type/instance, WordPress-menu-editor style.
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public ?string $html,
        public bool $targetBlank,
        public array $children,
        public array $cssClasses,
    ) {}
}
