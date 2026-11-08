<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** Turns a storefront slug into a fully resolved archetype page (content + block context), consulting ArchetypeRegistry, the merchant's own override, and the theme's default — so Storefront's controllers never need to know any of that exists. */
interface ArchetypeResolver
{
    public function resolve(string $slug, string $locale): ?ResolvedArchetypePage;

    public function resolveKey(string $key, string $slug, string $locale): ?ResolvedArchetypePage;
}
