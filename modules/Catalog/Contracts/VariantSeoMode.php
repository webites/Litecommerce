<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/**
 * How a product's variant deep-link pages (/products/{slug}/{axis}/{value}/...)
 * are treated for indexing — see docs/knowledge-base/09-presentation.md's
 * "Języki i SEO" section. CanonicalToProduct is the only mode that existed
 * before this enum: every variant page canonicals to the base product and
 * carries no independent SEO signal. Noindex only affects variant deep-link
 * pages, never the base product page itself.
 */
enum VariantSeoMode: string
{
    case CanonicalToProduct = 'canonical_to_product';
    case IndexedSeparately = 'indexed_separately';
    case Noindex = 'noindex';
}
