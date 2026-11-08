<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Lets core (the built-in cart/account/language-switcher/dark-mode-toggle
 * controls) and plugins alike contribute a pickable "widget" menu-item type
 * for the panel's menu builder — a plugin registers from its own
 * PluginServiceProvider::boot(), exactly as it would for a StorefrontSlotRegistry
 * slot. Cms's own MenuRepository never resolves a widget's HTML itself (it
 * only knows the item is type=widget with a key) — that stays entirely in
 * Storefront, since a widget typically needs request/session/route state
 * (the cart route, the auth:customer guard, the theme-mode cookie) that
 * already lives here.
 */
interface MenuWidgetRegistry
{
    /**
     * @param  string  $extensionId  'core' for the built-in widgets, else the plugin's own id.
     * @param  \Closure(?string $labelOverride): string  $render  Full HTML fragment for this widget; exceptions are caught and logged, and the menu item is simply omitted — never break the page, same contract as StorefrontSlotRegistry/ProductTabRegistry.
     */
    public function register(string $key, string $extensionId, string $label, \Closure $render): void;

    /** @return list<array{key: string, extensionId: string, label: string}> Every registered widget, in registration order — for the panel's "Widżety" tab. */
    public function all(): array;

    /** Null (never throws) when $key is unregistered or its render() closure failed. */
    public function render(string $key, ?string $labelOverride): ?string;
}
