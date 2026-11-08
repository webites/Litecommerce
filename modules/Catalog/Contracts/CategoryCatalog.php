<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;

/** Public, flat category read for consumers outside Catalog (e.g. Cms's category-mode product grid) that only need id/name pairs, not the panel's full tree management. */
interface CategoryCatalog
{
    /** @return list<CategorySummaryData> */
    public function listAll(string $locale): array;

    /** Null when the category doesn't exist or has no translation in $locale. Registered as the 'category' archetype's resolveInstance, see ArchetypeRegistry. */
    public function find(int $id, string $locale): ?CategorySummaryData;

    /** Registered as the 'category' archetype with Cms\Contracts\ArchetypeRegistry — matches a storefront slug against a category's own translated slug. */
    public function matchArchive(string $slug, string $locale): ?ArchetypeMatch;

    /** The SAME category's own slug in another locale — null when no translation exists in that locale. Registered as 'category' archetype's findSlugInLocale, see ArchetypeRegistry. */
    public function findSlugInLocale(int $id, string $locale): ?string;
}
