<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Response;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Blog\Contracts\BlogCategoryData;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Catalog\Contracts\BrandCatalog;
use Litecommerce\Modules\Catalog\Contracts\BrandSummaryData;
use Litecommerce\Modules\Catalog\Contracts\CategoryCatalog;
use Litecommerce\Modules\Catalog\Contracts\CategorySummaryData;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;
use Litecommerce\Modules\Catalog\Contracts\VariantUrlBuilder;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Storefront\Contracts\StorefrontCacheGroups;

/**
 * /sitemap.xml is ONE flat index — Google rejects an index that points at
 * another index ("Nested indexing" error in Search Console) — so language
 * and content type are both encoded in the per-file path
 * (/{locale}/sitemap-{type}.xml) rather than in nested indexes. hreflang is
 * NOT repeated here: it's already declared as HTML <link> tags on every page
 * (see StorefrontServiceProvider), and Google's own guidance is to pick one
 * method, not both. No <lastmod> either — no DTO here carries updated_at,
 * and the protocol doesn't require it. A noindex language is excluded
 * entirely, same as an inaccurate reasoning would exclude it from a manually
 * curated sitemap: a sitemap should only ever list indexable URLs.
 */
final class SitemapController extends Controller
{
    private const PER_PAGE = 200;

    private const TTL_SECONDS = 3600;

    public function __construct(private readonly ObjectCache $cache) {}

    public function index(LanguageRepository $languages, StoreSettingsReader $settings): Response
    {
        $locales = $this->indexableLocales($languages);
        $types = ['products', 'categories', 'brands', 'pages'];

        if ($settings->current()->blogEnabled) {
            $types[] = 'posts';
            $types[] = 'post-categories';
        }

        $entries = [];
        foreach ($locales as $locale) {
            foreach ($types as $type) {
                $entries[] = url("/{$locale}/sitemap-{$type}.xml");
            }
        }

        return $this->xmlResponse($this->cached('index', 'root', fn (): string => $this->sitemapIndexXml($entries)));
    }

    public function products(string $locale, ProductCatalog $products, VariantUrlBuilder $variantUrls): Response
    {
        return $this->xmlResponse($this->cached('products', $locale, function () use ($locale, $products, $variantUrls): string {
            $locs = [];
            $page = 1;

            do {
                $result = $products->paginatePublished($locale, null, self::PER_PAGE, $page);

                foreach ($result['items'] as $item) {
                    $baseUrl = route('storefront.catalog.show', ['locale' => $locale, 'slug' => $item->slug]);
                    $locs[] = $baseUrl;

                    if ($item->variantSeoMode === VariantSeoMode::IndexedSeparately->value) {
                        foreach ($this->variantUrlsFor($item->slug, $locale, $products, $variantUrls) as $variantUrl) {
                            $locs[] = $baseUrl.'/'.$variantUrl;
                        }
                    }
                }

                $page++;
            } while ($page <= (int) ceil($result['total'] / self::PER_PAGE));

            return $this->urlsetXml($locs);
        }));
    }

    public function categories(string $locale, CategoryCatalog $categories): Response
    {
        return $this->xmlResponse($this->cached('categories', $locale, fn (): string => $this->urlsetXml(array_map(
            static fn (CategorySummaryData $category): string => route('storefront.cms.show', ['locale' => $locale, 'slug' => $category->slug]),
            $categories->listAll($locale),
        ))));
    }

    public function brands(string $locale, BrandCatalog $brands): Response
    {
        return $this->xmlResponse($this->cached('brands', $locale, fn (): string => $this->urlsetXml(array_map(
            static fn (BrandSummaryData $brand): string => route('storefront.cms.show', ['locale' => $locale, 'slug' => $brand->slug]),
            $brands->listAll($locale),
        ))));
    }

    public function pages(string $locale, CmsPageRepository $pages): Response
    {
        return $this->xmlResponse($this->cached('pages', $locale, function () use ($locale, $pages): string {
            $locs = [url("/{$locale}")]; // the homepage lives at the locale root, not its own slug

            foreach ($pages->listAllPublishedSlugs($locale) as $slug) {
                $locs[] = route('storefront.cms.show', ['locale' => $locale, 'slug' => $slug]);
            }

            return $this->urlsetXml($locs);
        }));
    }

    public function posts(string $locale, BlogCatalog $blog): Response
    {
        return $this->xmlResponse($this->cached('posts', $locale, function () use ($locale, $blog): string {
            $locs = [];
            $page = 1;

            do {
                $result = $blog->paginatePublished($locale, null, self::PER_PAGE, $page);

                foreach ($result['items'] as $post) {
                    $locs[] = route('storefront.blog.show', ['locale' => $locale, 'slug' => $post->slug]);
                }

                $page++;
            } while ($page <= (int) ceil($result['total'] / self::PER_PAGE));

            return $this->urlsetXml($locs);
        }));
    }

    public function postCategories(string $locale, BlogCatalog $blog): Response
    {
        return $this->xmlResponse($this->cached('post-categories', $locale, fn (): string => $this->urlsetXml(array_map(
            static fn (BlogCategoryData $category): string => route('storefront.blog.category', ['locale' => $locale, 'slug' => $category->slug]),
            $blog->listCategories($locale),
        ))));
    }

    /** @return list<string> "{axis-slug}/{value-slug}/..." segments joined, one per real variant — never an incomplete/stale combination, see VariantUrlBuilder. */
    private function variantUrlsFor(string $slug, string $locale, ProductCatalog $products, VariantUrlBuilder $variantUrls): array
    {
        $product = $products->findBySlug($slug, $locale);

        if ($product === null || $product->optionAxes === []) {
            return [];
        }

        $paths = [];
        foreach ($product->variants as $variant) {
            $segments = $variantUrls->canonicalSegments($product->optionAxes, $variant->optionValues);

            if ($segments !== null) {
                $paths[] = implode('/', $segments);
            }
        }

        return $paths;
    }

    /** @return list<string> */
    private function indexableLocales(LanguageRepository $languages): array
    {
        return array_values(array_map(
            static fn (LanguageData $language): string => $language->code,
            array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled && ! $language->noindex),
        ));
    }

    private function cached(string $type, string $locale, Closure $build): string
    {
        $value = $this->cache->remember(
            StorefrontCacheGroups::SITEMAPS,
            "{$type}:{$locale}",
            $build,
            self::TTL_SECONDS,
        );

        return is_string($value) ? $value : '';
    }

    /** @param list<string> $sitemapUrls */
    private function sitemapIndexXml(array $sitemapUrls): string
    {
        $entries = implode('', array_map(
            static fn (string $url): string => '<sitemap><loc>'.htmlspecialchars($url, ENT_QUOTES | ENT_XML1).'</loc></sitemap>',
            $sitemapUrls,
        ));

        return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</sitemapindex>';
    }

    /** @param list<string> $locs */
    private function urlsetXml(array $locs): string
    {
        $entries = implode('', array_map(
            static fn (string $loc): string => '<url><loc>'.htmlspecialchars($loc, ENT_QUOTES | ENT_XML1).'</loc></url>',
            $locs,
        ));

        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>';
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
