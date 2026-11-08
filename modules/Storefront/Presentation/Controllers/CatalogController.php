<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductOptionAxisData;
use Litecommerce\Modules\Catalog\Contracts\ProductOptionValueData;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Catalog\Contracts\VariantSummaryData;
use Litecommerce\Modules\Catalog\Contracts\VariantUrlBuilder;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Contracts\ProductPageData;
use Litecommerce\Modules\Storefront\Contracts\ProductSearchListingBuilder;
use Litecommerce\Modules\Storefront\Contracts\ProductVariantOfferData;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;
use Litecommerce\Modules\Warehouse\Contracts\StockItemData;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;

/**
 * Composes read models from Catalog/Pricing/Warehouse into the page DTOs
 * Contracts declares — "Interfejs użytkownika może łączyć dane wielu
 * modułów" (docs/knowledge-base/02-architecture.md), the same rule
 * Catalog\Presentation\Controllers\ProductController already relies on for
 * the panel's product form.
 */
final class CatalogController extends Controller
{
    public function index(Request $request, string $locale, ProductSearchListingBuilder $listings, ThemeRenderer $renderer): View
    {
        $categoryId = $request->integer('category') ?: null;
        $listing = $listings->build($locale, $categoryId, $request->query());

        return $renderer->render('catalog.index', [
            'page' => $listing->page,
            'locale' => $locale,
            'categoryId' => $categoryId,
            'searchCriteria' => $listing->criteria,
            'searchFilters' => $listing->filters,
        ]);
    }

    /**
     * $locale, $slug and $variantPath are all declared (rather than reading
     * $locale from $request->route()) because Laravel fills a controller
     * method's non-class-typed parameters from the route's raw parameter
     * list POSITIONALLY, in URI segment order — with only $slug declared,
     * it silently received the {locale} segment's value instead.
     * $variantPath is declared LAST, after every injected dependency, and
     * NOT next to $locale/$slug where it would "belong" URL-wise: PHP
     * reflects a defaulted parameter followed by a required one (any of
     * $products.. $renderer below, none of which have defaults) as NOT
     * having an available default at all (a real, if easy to miss, PHP
     * behavior — triggers a "declared before required" deprecation too).
     * Laravel's dependency resolver then has nothing to splice into the
     * {variantPath?} gap left by the absent route segment, and every
     * later class dependency silently shifts one slot to fill it instead.
     * Putting $variantPath after all class params sidesteps this entirely.
     */
    public function show(Request $request, string $locale, string $slug, ProductCatalog $products, PriceQuoter $prices, StockLevelCatalog $stock, ProductListingItemAssembler $assembler, CurrencyRepository $currencies, ThemeRenderer $renderer, VariantUrlBuilder $variantUrls, RedirectResolver $redirects, ?string $variantPath = null): View|RedirectResponse
    {
        $product = $products->findBySlug($slug, $locale);

        if ($product === null) {
            $target = $redirects->resolve($locale, "/products/{$slug}");
            if ($target !== null) {
                return redirect("/{$locale}{$target}", 301);
            }
        }

        abort_if($product === null, 404);

        $preselectedOptionValues = $this->matchVariantPath($product->optionAxes, $variantPath);
        $preselectedOptionSignature = null;

        if ($preselectedOptionValues !== null) {
            $canonicalSegments = $variantUrls->canonicalSegments($product->optionAxes, $preselectedOptionValues);
            abort_if($canonicalSegments === null, 404);
            $canonicalPath = implode('/', $canonicalSegments);

            if (trim($variantPath ?? '', '/') !== $canonicalPath) {
                return redirect(route('storefront.catalog.show', ['locale' => $locale, 'slug' => $slug]).'/'.$canonicalPath, 301);
            }

            $preselectedOptionSignature = $this->optionSignature($product->optionAxes, $preselectedOptionValues);
            abort_unless($this->variantSignatureExists($product->optionAxes, $product->variants, $preselectedOptionSignature), 404);
        }

        $baseCurrency = $currencies->base();
        $currencyCode = $baseCurrency === null ? 'PLN' : $baseCurrency->code;
        $currencyDecimalPlaces = $baseCurrency === null ? 2 : $baseCurrency->decimalPlaces;
        $variantIds = array_map(static fn ($variant): int => $variant->id, $product->variants);
        $stockByVariant = $stock->findMany($variantIds);

        $offers = array_map(
            function ($variant) use ($prices, $currencyCode, $stockByVariant, $product): ProductVariantOfferData {
                $quote = $prices->quote($variant->id, $currencyCode, '1');

                return new ProductVariantOfferData(
                    variantId: $variant->id,
                    sku: $variant->sku,
                    gtin: $variant->gtin,
                    mpn: $variant->mpn,
                    displayName: $variant->displayName,
                    enabled: $variant->enabled,
                    unitAmount: $quote?->unitEffectiveAmount,
                    regularUnitAmount: $quote?->regularUnitAmount,
                    promotionalUnitAmount: $quote?->promotionalUnitAmount,
                    promotionalActive: $quote !== null && $quote->promotionalActive,
                    available: $variant->enabled && $quote !== null && $this->isAvailable($variant->productType, $stockByVariant[$variant->id] ?? null),
                    optionValues: $variant->optionValues,
                    optionSignature: $this->optionSignature($product->optionAxes, $variant->optionValues),
                    shortDescription: $variant->shortDescription,
                    descriptionHtml: $variant->descriptionHtml,
                );
            },
            $product->variants,
        );

        return $renderer->render('catalog.show', [
            'page' => new ProductPageData(
                $product,
                $offers,
                $currencyCode,
                $currencyDecimalPlaces,
                upsells: $assembler->assemble($product->upsells, $currencyCode),
                crossSells: $assembler->assemble($product->crossSells, $currencyCode),
                preselectedOptionValues: $preselectedOptionValues,
                preselectedOptionSignature: $preselectedOptionSignature,
            ),
            'locale' => $locale,
        ]);
    }

    /**
     * Parses the "{axis-slug}/{value-slug}/..." tail of a product URL into an
     * axis-key => value-key map — the same shape optionSignature() expects.
     * Returns null when $variantPath is absent/empty (the base product URL,
     * nothing preselected). Any malformed or unresolvable path 404s outright
     * rather than falling back to the base product, since "no editable
     * variant slug" (docs/knowledge-base/09-presentation.md) means every
     * valid combination has exactly one correct URL.
     *
     * @param  list<ProductOptionAxisData>  $optionAxes
     * @return ?array<string, string>
     */
    private function matchVariantPath(array $optionAxes, ?string $variantPath): ?array
    {
        $trimmed = trim($variantPath ?? '', '/');

        if ($trimmed === '') {
            return null;
        }

        abort_if($optionAxes === [], 404);

        $segments = explode('/', $trimmed);

        abort_if(count($segments) % 2 !== 0, 404);
        abort_if(count($segments) !== count($optionAxes) * 2, 404);

        $axesBySlug = [];
        foreach ($optionAxes as $axis) {
            $axesBySlug[$axis->slug] = $axis;
        }

        $resolved = [];
        for ($i = 0; $i < count($segments); $i += 2) {
            $axis = $axesBySlug[$segments[$i]] ?? null;
            abort_if($axis === null, 404);
            abort_if(array_key_exists($axis->key, $resolved), 404);

            $value = $this->findAxisValueBySlug($axis, $segments[$i + 1]);
            abort_if($value === null, 404);

            $resolved[$axis->key] = $value->key;
        }

        return $resolved;
    }

    private function findAxisValueBySlug(ProductOptionAxisData $axis, string $slug): ?ProductOptionValueData
    {
        foreach ($axis->values as $value) {
            if ($value->slug === $slug) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<ProductOptionAxisData>  $optionAxes
     * @param  list<VariantSummaryData>  $variants  already enabled-only, see ProductCatalog::findBySlug()
     */
    private function variantSignatureExists(array $optionAxes, array $variants, string $signature): bool
    {
        foreach ($variants as $variant) {
            if ($this->optionSignature($optionAxes, $variant->optionValues) === $signature) {
                return true;
            }
        }

        return false;
    }

    /**
     * The data-storefront-show-if condition several independent partials
     * (variant picker, add-to-cart, product details, ...) each key their
     * own per-offer block on, so picking values in ONE of them is reflected
     * in all the others without those partials needing to know about each
     * other. Built from EVERY axis the product currently offers, not just
     * the ones this specific offer happens to carry — an offer created
     * before a later-added axis existed would otherwise be missing that
     * axis's condition entirely and match ANY value of it (see
     * EloquentProductCatalog::optionAxesAndAssignments()'s doc comment for
     * why such offers can exist) — "__missing__" can never equal a real
     * radio value, so a stale/incomplete offer simply never resolves via
     * the picker instead of over-matching another, complete one.
     *
     * @param  list<ProductOptionAxisData>  $optionAxes
     * @param  array<string, string>  $optionValues
     */
    private function optionSignature(array $optionAxes, array $optionValues): string
    {
        return implode(',', array_map(
            static fn ($axis): string => $axis->key.':'.($optionValues[$axis->key] ?? '__missing__'),
            $optionAxes,
        ));
    }

    private function isAvailable(string $productType, ?StockItemData $stockItem): bool
    {
        if ($productType === ProductType::Digital->value) {
            return true;
        }

        if ($stockItem === null) {
            return false;
        }

        return bccomp($stockItem->availableQuantity, '0', 6) > 0 || $stockItem->allowBackorder;
    }
}
