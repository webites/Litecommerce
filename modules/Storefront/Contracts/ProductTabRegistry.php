<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Lets a plugin add a whole extra tab to the product page (its own button
 * in the tab bar plus its own panel), not just a fragment inside an
 * existing one — the structured sibling of StorefrontSlotRegistry, which
 * only concatenates opaque HTML into a single named spot. A plugin
 * registers from its own PluginServiceProvider::boot(), exactly as it
 * would for a slot; see partials/product-tabs.blade.php for how the
 * default theme renders these alongside its own built-in Opis/Specyfikacja
 * tabs — a theme without that partial (or a custom one that never reads
 * this registry) simply never shows plugin tabs, the same "opt-in
 * markup, never forced" contract as every other engine primitive.
 */
interface ProductTabRegistry
{
    /**
     * $id becomes the tab's data-storefront-show-if value
     * ("product_tab:{$id}") and its radio's value — pick something
     * specific to your plugin (e.g. your extension id) to avoid colliding
     * with another plugin's tab or the theme's own "opis"/"specyfikacja".
     *
     * @param  \Closure(ProductTabContext): string  $render  Returns the panel's HTML fragment for this product; exceptions are caught and logged, and just omit this one tab — never break the page, same contract as StorefrontSlotRegistry.
     */
    public function register(string $id, string $extensionId, string $label, \Closure $render): void;

    /** @return list<ProductTabData> Every successfully-rendered plugin tab, in registration order. */
    public function all(ProductTabContext $context): array;
}
