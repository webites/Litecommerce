<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;

/** Public brand read for consumers outside Catalog — today only the 'brand' archetype registration (see CatalogServiceProvider::boot()); brand CRUD itself stays panel-only (CatalogReferenceController). */
interface BrandCatalog
{
    /** @return list<BrandSummaryData> */
    public function listAll(string $locale): array;

    /** Null when the brand doesn't exist or has no translation in $locale. Registered as the 'brand' archetype's resolveInstance, see ArchetypeRegistry. */
    public function find(int $id, string $locale): ?BrandSummaryData;

    /** Registered as the 'brand' archetype with Cms\Contracts\ArchetypeRegistry — matches a storefront slug against a brand's own translated slug. */
    public function matchArchive(string $slug, string $locale): ?ArchetypeMatch;

    /** The SAME brand's own slug in another locale — null when no translation exists in that locale. Registered as 'brand' archetype's findSlugInLocale, see ArchetypeRegistry. */
    public function findSlugInLocale(int $id, string $locale): ?string;
}
