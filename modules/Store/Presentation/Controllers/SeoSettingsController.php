<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Litecommerce\Modules\Catalog\Contracts\CatalogSeoSettingsRepository;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

/**
 * Deliberately small: per-language noindex/sitemap inclusion lives on the
 * "Języki" screen (it's a language-wide setting, not a global SEO one). The
 * default variant indexing mode below is read through Catalog's own contract
 * (Catalog owns products/variants) — the form on this page submits to
 * Catalog's own write endpoint, see CatalogSeoSettingsController's docblock.
 */
final class SeoSettingsController extends Controller
{
    public function __construct(private readonly CatalogSeoSettingsRepository $catalogSeoSettings) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('seo', [
            'sitemapUrl' => url('/sitemap.xml'),
            'robotsUrl' => url('/robots.txt'),
            'defaultVariantSeoMode' => $this->catalogSeoSettings->current()->defaultVariantSeoMode,
            'variantSeoModes' => [
                ['value' => VariantSeoMode::CanonicalToProduct->value, 'label' => trans('store::panel.seo.variant_seo_modes.canonical_to_product')],
                ['value' => VariantSeoMode::IndexedSeparately->value, 'label' => trans('store::panel.seo.variant_seo_modes.indexed_separately')],
                ['value' => VariantSeoMode::Noindex->value, 'label' => trans('store::panel.seo.variant_seo_modes.noindex')],
            ],
        ]);
    }
}
