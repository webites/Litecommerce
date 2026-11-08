<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Application\ResetStorefrontToThemeDefaults;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Cms\Application\ResetStorefrontToThemeDefaults — the action behind the
 * "set this theme's defaults?" modal offered right after activating a theme
 * (Themes\Presentation\Controllers\Panel\ThemeController::activate()). Wider
 * than ResetHomepageToThemeDefault alone: also deletes every archetype
 * override, but never touches a merchant's own one-off Cms pages.
 */
final class ResetStorefrontToThemeDefaultsTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_deletes_every_archetype_override_but_leaves_a_plain_page_untouched(): void
    {
        $category = CmsPage::query()->create(['status' => PageStatus::Published->value, 'is_homepage' => false, 'archetype' => 'category']);
        CmsPageTranslation::query()->create(['page_id' => $category->getKey(), 'locale' => 'pl', 'slug' => '@archetype/category', 'title' => 'Kategoria', 'content' => ['content' => []]]);
        $brand = CmsPage::query()->create(['status' => PageStatus::Published->value, 'is_homepage' => false, 'archetype' => 'brand']);
        CmsPageTranslation::query()->create(['page_id' => $brand->getKey(), 'locale' => 'pl', 'slug' => '@archetype/brand', 'title' => 'Marka', 'content' => ['content' => []]]);
        $plainPage = CmsPage::query()->create(['status' => PageStatus::Published->value, 'is_homepage' => false]);
        CmsPageTranslation::query()->create(['page_id' => $plainPage->getKey(), 'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas', 'seo_title' => 'O nas — sklep', 'content' => ['content' => [['type' => 'rich-text', 'props' => ['html' => '<p>Historia</p>']]]]]);

        app(ResetStorefrontToThemeDefaults::class)->handle('pl');

        $this->assertDatabaseMissing('cms_pages', ['id' => $category->getKey()]);
        $this->assertDatabaseMissing('cms_pages', ['id' => $brand->getKey()]);
        $this->assertDatabaseMissing('cms_page_translations', ['page_id' => $category->getKey()]);
        $this->assertDatabaseMissing('cms_page_translations', ['page_id' => $brand->getKey()]);

        $this->assertDatabaseHas('cms_pages', ['id' => $plainPage->getKey(), 'status' => PageStatus::Published->value]);
        $translation = DB::table('cms_page_translations')->where('page_id', $plainPage->getKey())->first();
        $this->assertSame('o-nas', $translation->slug);
        $this->assertSame('O nas — sklep', $translation->seo_title);
        $this->assertSame(['content' => [['type' => 'rich-text', 'props' => ['html' => '<p>Historia</p>']]]], json_decode((string) $translation->content, true));
    }

    public function test_also_resets_the_homepage_like_reset_homepage_to_theme_default_alone_does(): void
    {
        $page = app(ResetStorefrontToThemeDefaults::class)->handle('pl');

        $this->assertTrue($page->is_homepage);
        $this->assertSame(PageStatus::Published->value, $page->status);
    }
}
