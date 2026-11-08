<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * Storefront-facing rendering of the theme::partials.nav-menu partial
 * (Storefront\Infrastructure\RouteAwareMenuResolver) — see
 * tests/Feature/Panel/MenuManagementTest.php for the panel-side CRUD.
 */
final class StorefrontNavMenuTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_a_fresh_store_with_no_menu_configured_yet_renders_no_nav_at_all(): void
    {
        // The menu is entirely merchant-configured in the panel — no code-level
        // default that guesses at products/cart/switchers on their behalf.
        // No menu also means no hamburger: there is nothing for it to open.
        $response = $this->get('/pl');

        $response->assertOk();
        $response->assertDontSee('sf-menu', false);
        $response->assertDontSee('data-storefront-nav-toggle', false);
        $response->assertDontSee('data-storefront="cart-count"', false);
    }

    public function test_the_hamburger_only_appears_once_a_menu_actually_has_items(): void
    {
        // The toggle markup/behavior is core (public/storefront/runtime.js's
        // data-storefront-nav-toggle), but theme::partials.nav-menu only
        // emits it alongside the menu it opens — never an empty drawer.
        DB::table('menus')->insert([
            'name' => 'Menu główne', 'location_id' => 'primary',
            'content' => json_encode([
                ['id' => 'cart', 'type' => 'widget', 'referenceKey' => 'cart', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->get('/pl');

        $response->assertOk();
        $response->assertSee('data-storefront-nav', false);
        $response->assertSee('data-storefront-nav-toggle', false);
        $response->assertSee('data-storefront-nav-close', false);
        $response->assertSee('aria-controls="sf-primary-nav"', false);
    }

    public function test_a_custom_menu_renders_a_nested_submenu_in_order(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->app['auth']->forgetGuards();

        $tree = [
            ['id' => 'a', 'type' => 'page', 'referenceId' => (string) $pageId, 'referenceKey' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => [
                ['id' => 'b', 'type' => 'custom', 'url' => 'https://example.com', 'labelOverride' => 'Zewnętrzny', 'referenceKey' => null, 'referenceId' => null, 'targetBlank' => true, 'children' => []],
            ]],
            ['id' => 'c', 'type' => 'widget', 'referenceKey' => 'cart', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
        ];
        DB::table('menus')->insert(['name' => 'Menu główne', 'location_id' => 'primary', 'content' => json_encode($tree), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->get('/pl');

        $response->assertOk();
        $response->assertSeeInOrder(['O nas', 'Zewnętrzny'], false);
        $response->assertSee('href="/pl/o-nas"', false);
        $response->assertSee('href="https://example.com"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener"', false);
        $response->assertSee('data-storefront="cart-count"', false);
        $response->assertSee('data-storefront-submenu', false);
        // WordPress-style "what is this item" CSS hooks for theme/plugin styling.
        $response->assertSee('class="sf-menu sf-menu-primary"', false);
        $response->assertSee('class="sf-menu-item sf-menu-item-page sf-menu-item-page-'.$pageId.' sf-menu-item-has-children"', false);
        $response->assertSee('class="sf-menu-item sf-menu-item-custom"', false);
        $response->assertSee('class="sf-menu-item sf-menu-item-widget sf-menu-item-widget-cart"', false);
    }

    public function test_a_menu_item_pointing_at_a_deleted_category_is_silently_omitted(): void
    {
        $tree = [
            ['id' => 'a', 'type' => 'archetype', 'referenceKey' => 'category', 'referenceId' => '999999', 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ['id' => 'b', 'type' => 'widget', 'referenceKey' => 'cart', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
        ];
        DB::table('menus')->insert(['name' => 'Menu główne', 'location_id' => 'primary', 'content' => json_encode($tree), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->get('/pl');

        $response->assertOk();
        $response->assertSee('data-storefront="cart-count"', false);
    }

    public function test_a_menu_item_pointing_at_an_unpublished_page_is_silently_omitted(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'szkic', 'title' => 'Szkic',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->app['auth']->forgetGuards();

        $tree = [
            ['id' => 'a', 'type' => 'page', 'referenceId' => (string) $pageId, 'referenceKey' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
        ];
        DB::table('menus')->insert(['name' => 'Menu główne', 'location_id' => 'primary', 'content' => json_encode($tree), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->get('/pl');

        $response->assertOk();
        $response->assertDontSee('Szkic', false);
    }

    public function test_an_empty_footer_location_renders_nothing(): void
    {
        $response = $this->get('/pl');

        $response->assertOk();
        // The primary fallback still renders (see the first test) but nothing
        // was ever assigned to 'footer', so its own <ul class="sf-menu"> must
        // be entirely absent rather than an empty shell.
        $footerStart = strpos($response->getContent(), '<footer');
        $footerHtml = substr($response->getContent(), $footerStart);
        $this->assertStringNotContainsString('sf-menu', $footerHtml);
    }

    private function zipDirectory(string $sourceDir): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();

        return $zipPath;
    }
}
