<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Redirects\Infrastructure\Models\Redirect;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * None of these paths ever need real destination content or an installed
 * theme — a matching Redirect makes CatalogController/CmsPageController/
 * BlogController return before ThemeRenderer is ever touched (see each
 * controller's abort_if site), and the "no redirect" cases 404 the same way
 * without rendering anything either.
 */
final class RedirectResolutionTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_product_path_redirects_when_a_matching_redirect_exists(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/products/stary-produkt', 'to_path' => '/products/nowy-produkt']);

        $this->get('/pl/products/stary-produkt')->assertRedirect('/pl/products/nowy-produkt')->assertStatus(301);
    }

    public function test_flat_cms_or_archetype_path_redirects_when_a_matching_redirect_exists(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/stara-strona', 'to_path' => '/nowa-strona']);

        $this->get('/pl/stara-strona')->assertRedirect('/pl/nowa-strona')->assertStatus(301);
    }

    public function test_blog_post_path_redirects_when_a_matching_redirect_exists(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/blog/stary-wpis', 'to_path' => '/blog/nowy-wpis']);

        $this->get('/pl/blog/stary-wpis')->assertRedirect('/pl/blog/nowy-wpis')->assertStatus(301);
    }

    public function test_blog_category_path_redirects_when_a_matching_redirect_exists(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/blog/category/stara-kategoria', 'to_path' => '/blog/category/nowa-kategoria']);

        $this->get('/pl/blog/category/stara-kategoria')->assertRedirect('/pl/blog/category/nowa-kategoria')->assertStatus(301);
    }

    public function test_a_path_shape_matching_no_route_pattern_falls_back_to_the_redirect_lookup(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a/b/c', 'to_path' => '/nowa-strona']);

        $this->get('/pl/a/b/c')->assertRedirect('/pl/nowa-strona')->assertStatus(301);
    }

    public function test_fallback_404s_for_an_unrecognised_locale_segment(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a/b/c', 'to_path' => '/nowa-strona']);

        $this->get('/xx/a/b/c')->assertNotFound();
    }

    public function test_no_matching_redirect_and_no_content_still_404s(): void
    {
        $this->get('/pl/nieznany-adres')->assertNotFound();
        $this->get('/pl/blog/nieznany-wpis')->assertNotFound();
        $this->get('/pl/a/b/c')->assertNotFound();
    }
}
