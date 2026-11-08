<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Cms\Contracts\ArchetypeResolver;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Cms\Contracts\CmsPageData;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Search\Contracts\ProductSearchCriteria;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Contracts\ProductListingPageData;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

/** Composes Cms + Catalog/Pricing/Store contracts into theme-rendered pages — the same role CatalogController plays for product pages (see its own docblock). */
final class CmsPageController extends Controller
{
    /**
     * Falls back to the plain product listing when no CMS page is flagged
     * as the homepage yet — so a store upgrading into this feature never
     * loses its homepage, and a fresh store works before anyone has
     * touched the page builder.
     */
    public function home(
        Request $request,
        string $locale,
        CmsPageRepository $pages,
        BlockRenderer $blocks,
        ThemeRenderer $renderer,
        ProductCatalog $products,
        ProductListingItemAssembler $assembler,
        CurrencyRepository $currencies,
        StoreSettingsReader $settings,
    ): View {
        $page = $pages->findPublishedHomepage($locale);

        if ($page !== null) {
            return $renderer->render('cms.page', [
                'page' => $page,
                'blocksHtml' => $blocks->render($page->content, $locale),
                'locale' => $locale,
            ]);
        }

        $requestedPage = max(1, $request->integer('page', 1));
        $baseCurrency = $currencies->base();
        $currencyCode = $baseCurrency === null ? 'PLN' : $baseCurrency->code;
        $perPage = $settings->current()->catalogProductsPerPage;
        $result = $products->paginatePublished($locale, null, $perPage, $requestedPage);

        return $renderer->render('catalog.index', [
            'page' => new ProductListingPageData($assembler->assemble($result['items'], $currencyCode), $result['total'], $requestedPage, $perPage),
            'locale' => $locale,
            'categoryId' => null,
            'searchCriteria' => new ProductSearchCriteria('', $locale, $currencyCode, null, null, null, false, [], $requestedPage, $perPage),
            'searchFilters' => [],
        ]);
    }

    /**
     * A plain Cms page (own slug) always wins; if none matches, tries every
     * registered archetype (category, brand, ...) via ArchetypeResolver — the
     * same flat slug, no dedicated URL prefix per archetype. See
     * docs/knowledge-base/09-presentation.md's archetype pages section.
     */
    public function show(Request $request, string $locale, string $slug, CmsPageRepository $pages, BlockRenderer $blocks, ThemeRenderer $renderer, ArchetypeResolver $archetypes, RedirectResolver $redirects): View|RedirectResponse
    {
        $page = $pages->findPublishedBySlug($slug, $locale);

        if ($page !== null) {
            return $renderer->render('cms.page', [
                'page' => $page,
                'blocksHtml' => $blocks->render($page->content, $locale),
                'locale' => $locale,
            ]);
        }

        $archetype = $archetypes->resolve($slug, $locale);

        if ($archetype === null) {
            $target = $redirects->resolve($locale, "/{$slug}");
            if ($target !== null) {
                return redirect("/{$locale}{$target}", 301);
            }
        }

        abort_if($archetype === null, 404);

        return $renderer->render('cms.page', [
            'page' => new CmsPageData(
                id: 0,
                slug: $slug,
                title: $archetype->title,
                seoTitle: null,
                seoDescription: is_string($archetype->blockContext['description'] ?? null) ? $archetype->blockContext['description'] : null,
                content: $archetype->content,
            ),
            'blocksHtml' => $blocks->render($archetype->content, $locale, $archetype->blockContext),
            'locale' => $locale,
        ]);
    }
}
