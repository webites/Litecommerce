<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/**
 * {{tag}} values available EVERYWHERE — any Cms page, any archetype template —
 * as opposed to ArchetypeRegistry's per-archetype tags (e.g. {{category_name}},
 * only meaningful while viewing a category). Empty by default: no built-in
 * global tag exists yet. The extension point itself is real today, though —
 * a future plugin or theme's ServiceProvider::boot() registers here the same
 * way Catalog registers its archetypes, and Cms\Infrastructure\ThemeBlockRenderer
 * already merges resolve() into every render, so nothing else needs to change
 * when the first one is added.
 */
interface GlobalTagRegistry
{
    /**
     * @param  string  $name  Stable tag name (e.g. 'store_name') — usable as {{name}} in any block's text prop.
     * @param  string  $label  Shown in the editor's dynamic-tags reference modal.
     * @param  \Closure(string $locale): string  $resolve  Exceptions are caught and logged — never break a page.
     */
    public function register(string $name, string $label, \Closure $resolve): void;

    /** @return list<array{name: string, label: string}> Every registered global tag, in registration order — for the editor's reference modal, independent of locale. */
    public function all(): array;

    /** @return array<string, string> Every tag's resolved value for this locale, ready to merge into ThemeBlockRenderer's interpolation. */
    public function resolve(string $locale): array;
}
