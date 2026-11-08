<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/**
 * Lets any module (or, later, a plugin) declare a whole class of aggregating
 * storefront pages — category archives, brand archives, a future blog index —
 * without Cms knowing anything about them ahead of time, the same "register
 * from your own ServiceProvider::boot()" idiom as Storefront\Contracts\
 * ProductTabRegistry. Cms\Infrastructure\ThemeArchetypeResolver then tries
 * every registered archetype's resolver, in registration order, against a
 * storefront slug — the WordPress "custom post type" analogue: a category or
 * brand page is reached through the SAME flat /{locale}/{slug} route as any
 * plain Cms page, not a dedicated URL prefix.
 */
interface ArchetypeRegistry
{
    /**
     * @param  string  $key  Stable identifier (e.g. 'category', 'brand') — keys the merchant's whole-archetype override (CmsPage::archetype) and the theme's default template (theme::archetypes.{key}).
     * @param  string  $label  Shown in the panel's "Szablony archiwów" screen.
     * @param  \Closure(string $slug, string $locale): ?ArchetypeMatch  $resolve  Exceptions are caught and logged by match() — never break the page, same contract as ProductTabRegistry.
     * @param  array<string, string>  $availableTags  Dynamic-tag NAME => human label (e.g. ['category_name' => 'Nazwa kategorii']) this archetype's resolver may populate on ArchetypeMatch::$tags — static metadata for the panel editor to show merchants what {{tag}} placeholders exist for THIS archetype, independent of any one matched instance.
     * @param  ?\Closure(array<string, mixed> $blockContext, string $locale): ?string  $findSlugInLocale  Given an ALREADY-matched instance's own blockContext (e.g. ['category_id' => 5]) and a target locale, returns that SAME instance's slug in that locale — powers a storefront language switcher that stays on the equivalent page (see Storefront\Contracts\LanguageAlternateResolver). Omit if this archetype has no per-instance slug (unlikely) or the feature isn't needed yet — a missing entry just falls back to that locale's homepage.
     * @param  ?\Closure(string $locale): list<array{id: string, label: string}>  $listInstances  Every instance of this archetype (e.g. every category), for an admin picker such as the menu builder's "Kategorie" tab. Omit if nothing needs to list instances of this archetype yet.
     * @param  ?\Closure(string $id, string $locale): ?array{title: string, slug: string}  $resolveInstance  Given a raw instance id (as produced by $listInstances) and a target locale, resolves its current title+slug — or null when the instance no longer exists/isn't published/has no translation in $locale, in which case the caller (e.g. a rendered menu item) must silently omit it.
     */
    public function register(string $key, string $label, \Closure $resolve, array $availableTags = [], ?\Closure $findSlugInLocale = null, ?\Closure $listInstances = null, ?\Closure $resolveInstance = null): void;

    /** @return list<array{key: string, label: string, availableTags: array<string, string>}> Every registered archetype, in registration order. */
    public function all(): array;

    /** Tries each registered resolver in registration order; the first non-null match wins. */
    public function match(string $slug, string $locale): ?ResolvedArchetype;

    /** Resolves only one registered key; prefixed routes use this to avoid collisions with flat archetypes carrying the same instance slug. */
    public function matchKey(string $key, string $slug, string $locale): ?ResolvedArchetype;

    /** @param array<string, mixed> $blockContext */
    public function findSlugInLocale(string $key, array $blockContext, string $locale): ?string;

    /** @return list<array{id: string, label: string}> Empty when $key is unregistered or registered no listInstances closure. */
    public function listInstances(string $key, string $locale): array;

    /** Null when $key is unregistered, has no resolveInstance closure, or the instance no longer exists/isn't published/has no translation in $locale. */
    public function resolveInstance(string $key, string $id, string $locale): ?array;
}
