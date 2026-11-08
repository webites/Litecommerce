<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Http\Request;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Storefront\Contracts\LanguageAlternateResolver;

/**
 * Dispatches on the CURRENT route's name (routes/storefront-pages.php) rather
 * than parsing the URL — every storefront page already has a distinct,
 * stable route name, so this never has to guess "what kind of page is this".
 * Bound as a singleton and memoizes its result per Request object: the view
 * composer that calls this fires once per theme:: view render (layout and
 * every block/partial in one request), but the answer can never change
 * within a single request. Keying by the Request instance itself (via a
 * WeakMap, rather than a plain instance property) keeps this correct — and
 * leak-free — even when the same container serves more than one request,
 * e.g. under Octane or in tests that issue several $this->get() calls
 * against the same application instance.
 */
final class RouteAwareLanguageAlternateResolver implements LanguageAlternateResolver
{
    /** @var \WeakMap<Request, array<string, string>> */
    private \WeakMap $cache;

    public function __construct(
        private readonly CmsPageRepository $pages,
        private readonly ArchetypeRegistry $archetypes,
        private readonly ProductCatalog $products,
        private readonly LanguageRepository $languages,
    ) {
        $this->cache = new \WeakMap;
    }

    public function resolve(Request $request, string $currentLocale): array
    {
        if (isset($this->cache[$request])) {
            return $this->cache[$request];
        }

        $route = $request->route();

        if ($route === null) {
            return $this->cache[$request] = [];
        }

        $otherLocales = array_values(array_diff($this->enabledLocales(), [$currentLocale]));

        $resolved = match ($route->getName()) {
            'storefront.home' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}"),
            'storefront.catalog.index' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/products"),
            'storefront.cart.show' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/cart"),
            'storefront.checkout.show' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/checkout"),
            'storefront.checkout.confirmation' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/checkout/{$route->parameter('draft')}/thank-you"),
            'storefront.account.login' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/login"),
            'storefront.account.register' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/register"),
            'storefront.account.preferences' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/preferences"),
            'storefront.account.delete' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/delete"),
            'storefront.account.orders.index' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/orders"),
            'storefront.account.orders.show' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/account/orders/{$route->parameter('order')}"),
            'storefront.catalog.show' => $this->productAlternates((string) $route->parameter('slug'), $currentLocale, $otherLocales),
            'storefront.blog.index' => $this->static($otherLocales, fn (string $locale): string => "/{$locale}/blog"),
            'storefront.blog.category' => $this->blogCategoryAlternates((string) $route->parameter('slug'), $currentLocale, $otherLocales),
            'storefront.blog.show' => $this->blogPostAlternates((string) $route->parameter('slug'), $currentLocale, $otherLocales),
            'storefront.cms.show' => $this->cmsOrArchetypeAlternates((string) $route->parameter('slug'), $currentLocale, $otherLocales),
            default => [],
        };

        $normalized = [];
        foreach ($resolved as $locale => $url) {
            if (is_string($locale) && is_string($url)) {
                $normalized[$locale] = $url;
            }
        }

        return $this->cache[$request] = $normalized;
    }

    /** @return list<string> */
    private function enabledLocales(): array
    {
        return array_values(array_map(
            static fn (LanguageData $language): string => $language->code,
            array_filter($this->languages->all(), static fn (LanguageData $language): bool => $language->isEnabled),
        ));
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function static(array $locales, \Closure $path): array
    {
        return array_combine($locales, array_map($path, $locales));
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function productAlternates(string $slug, string $currentLocale, array $locales): array
    {
        $product = $this->products->findBySlug($slug, $currentLocale);

        if ($product === null) {
            return [];
        }

        $result = [];
        foreach ($locales as $locale) {
            $altSlug = $this->products->findSlugInLocale($product->id, $locale);

            if ($altSlug !== null) {
                $result[$locale] = "/{$locale}/products/{$altSlug}";
            }
        }

        return $result;
    }

    /**
     * Mirrors CmsPageController::show()'s own resolution order: a plain Cms
     * page by slug first, then a registered archetype (category/brand/...).
     *
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function cmsOrArchetypeAlternates(string $slug, string $currentLocale, array $locales): array
    {
        $page = $this->pages->findPublishedBySlug($slug, $currentLocale);

        if ($page !== null) {
            $result = [];
            foreach ($locales as $locale) {
                $altSlug = $this->pages->findPublishedSlugInLocale($page->id, $locale);

                if ($altSlug !== null) {
                    $result[$locale] = "/{$locale}/{$altSlug}";
                }
            }

            return $result;
        }

        $resolved = $this->archetypes->match($slug, $currentLocale);

        if ($resolved === null) {
            return [];
        }

        $result = [];
        foreach ($locales as $locale) {
            $altSlug = $this->archetypes->findSlugInLocale($resolved->key, $resolved->match->blockContext, $locale);

            if ($altSlug !== null) {
                $result[$locale] = "/{$locale}/{$altSlug}";
            }
        }

        return $result;
    }

    /** @param list<string> $locales @return array<string, string> */
    private function blogPostAlternates(string $slug, string $currentLocale, array $locales): array
    {
        if (! app()->bound(BlogCatalog::class)) {
            return [];
        }

        $blog = app(BlogCatalog::class);
        $post = $blog->findPublishedPostBySlug($slug, $currentLocale);
        if ($post === null) {
            return [];
        }

        $result = [];
        foreach ($locales as $locale) {
            $alternate = $blog->findPostSlugInLocale($post->id, $locale);
            if ($alternate !== null) {
                $result[$locale] = "/{$locale}/blog/{$alternate}";
            }
        }

        return $result;
    }

    /** @param list<string> $locales @return array<string, string> */
    private function blogCategoryAlternates(string $slug, string $currentLocale, array $locales): array
    {
        if (! app()->bound(BlogCatalog::class)) {
            return [];
        }

        $blog = app(BlogCatalog::class);
        $match = $blog->matchCategoryArchive($slug, $currentLocale);
        $categoryId = $match === null || ! is_numeric($match->blockContext['blog_category_id'] ?? null)
            ? null
            : (int) $match->blockContext['blog_category_id'];
        if ($categoryId === null) {
            return [];
        }

        $result = [];
        foreach ($locales as $locale) {
            $alternate = $blog->findCategorySlugInLocale($categoryId, $locale);
            if ($alternate !== null) {
                $result[$locale] = "/{$locale}/blog/category/{$alternate}";
            }
        }

        return $result;
    }
}
