<?php

use App\Http\Middleware\EnsureBlogEnabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Litecommerce\Modules\Blog\Presentation\Controllers\Storefront\BlogController;
use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Storefront\Presentation\Controllers\AccountController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\CartPageController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\CatalogController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\CheckoutPageController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\CmsPageController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\CustomerOrderController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\GuestOrderController;
use Litecommerce\Modules\Storefront\Presentation\Controllers\SitemapController;
use Litecommerce\Modules\Storefront\Presentation\Middleware\ResolveStorefrontLocale;

// Storefront-facing PAGES (theme-rendered); routes/storefront.php stays the JSON API these pages' JS calls.
// Path segments below are always English (base language), independent of {locale} — see docs/knowledge-base/13-i18n.md.

Route::get('/', function (LanguageRepository $languages) {
    $default = $languages->default();
    abort_if($default === null, 503, trans('storefront::messages.no_language_configured'));

    return redirect("/{$default->code}");
})->name('storefront.home-redirect');

// One global, flat sitemap index — never per-locale, Google rejects an index
// referencing another index (see SitemapController's docblock).
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('storefront.sitemap.index');

Route::get('/robots.txt', function () {
    return response(
        "User-agent: *\nDisallow:\nSitemap: ".url('/sitemap.xml')."\n",
        200,
    )->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('storefront.robots');

Route::prefix('/{locale}')->middleware(ResolveStorefrontLocale::class)->name('storefront.')->group(function (): void {
    // Registered before the catch-all "/{slug}" below, same reason as
    // "/products" — a literal path must never be shadowed by the wildcard.
    Route::get('/sitemap-products.xml', [SitemapController::class, 'products'])->name('sitemap.products');
    Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
    Route::get('/sitemap-brands.xml', [SitemapController::class, 'brands'])->name('sitemap.brands');
    Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
    Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->middleware(EnsureBlogEnabled::class)->name('sitemap.posts');
    Route::get('/sitemap-post-categories.xml', [SitemapController::class, 'postCategories'])->middleware(EnsureBlogEnabled::class)->name('sitemap.post-categories');

    // "/" is a Cms page (the one flagged is_homepage), falling back to the plain
    // listing below when none exists yet — see CmsPageController::home().
    Route::get('/', [CmsPageController::class, 'home'])->name('home');
    Route::get('/products', [CatalogController::class, 'index'])->name('catalog.index');
    // {variantPath} carries zero or more "{axis-slug}/{value-slug}" pairs
    // identifying one exact variant combination (see CatalogController::show()).
    Route::get('/products/{slug}/{variantPath?}', [CatalogController::class, 'show'])->where('variantPath', '.*')->name('catalog.show');

    Route::middleware(EnsureBlogEnabled::class)->group(function (): void {
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    });

    Route::get('/cart', [CartPageController::class, 'show'])->middleware('no-store')->name('cart.show');

    Route::get('/checkout', [CheckoutPageController::class, 'show'])->middleware('no-store')->name('checkout.show');
    Route::get('/checkout/{draft}/thank-you', [CheckoutPageController::class, 'confirmation'])->middleware('no-store')->name('checkout.confirmation');

    Route::get('/account/login', [AccountController::class, 'loginForm'])->middleware('no-store')->name('account.login');
    Route::get('/account/register', [AccountController::class, 'registerForm'])->middleware('no-store')->name('account.register');
    Route::get('/account/forgot-password', [AccountController::class, 'forgotPasswordForm'])->middleware('no-store')->name('account.password.request');
    Route::get('/account/reset-password/{token}', [AccountController::class, 'resetPasswordForm'])->middleware('no-store')->name('account.password.reset');
    Route::get('/account/delete', [AccountController::class, 'deleteForm'])->middleware(['auth:customer', 'no-store'])->name('account.delete');
    Route::get('/account/delete/{token}', [AccountController::class, 'deleteConfirmForm'])->middleware('no-store')->where('token', '[A-Za-z0-9]{64}')->name('account.delete.confirm');
    Route::get('/account/preferences', [AccountController::class, 'preferences'])->middleware(['auth:customer', 'no-store'])->name('account.preferences');
    Route::get('/marketing/unsubscribe/{token}', [AccountController::class, 'unsubscribeForm'])->middleware('no-store')->where('token', '[A-Za-z0-9]{40}')->name('marketing.unsubscribe');
    Route::get('/orders/{token}', [GuestOrderController::class, 'show'])->middleware('no-store')->where('token', '[A-Za-z0-9_-]{40,64}')->name('orders.guest.show');

    Route::middleware(['auth:customer', 'no-store'])->prefix('/account/orders')->name('account.orders.')->group(function (): void {
        Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [CustomerOrderController::class, 'show'])->whereUuid('order')->name('show');
    });

    // Catch-all Cms page by slug — MUST stay last in this group so it never
    // shadows the explicit paths above (products/cart/checkout/account).
    Route::get('/{slug}', [CmsPageController::class, 'show'])->name('cms.show');
});

// A redirect's from_path can have any shape a merchant typed into
// the Redirects settings tab, including ones no route pattern above matches at all
// (e.g. more segments than the flat "/{locale}/{slug}" catch-all allows) —
// Laravel never reaches a controller for those, so they need their own
// last-resort check here rather than one of the abort_if() sites inside
// CatalogController/CmsPageController/BlogController.
Route::fallback(function (Request $request, LanguageRepository $languages, RedirectResolver $redirects) {
    $segments = explode('/', trim($request->path(), '/'));
    $locale = $segments[0] ?? '';
    $enabledCodes = array_map(
        static fn (LanguageData $language): string => $language->code,
        array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled)),
    );

    abort_unless(in_array($locale, $enabledCodes, true), 404);

    $path = '/'.implode('/', array_slice($segments, 1));
    $target = $redirects->resolve($locale, $path);

    abort_if($target === null, 404);

    return redirect("/{$locale}{$target}", 301);
});
