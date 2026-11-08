<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

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
 * The page builder's language switcher (Panel/Cms/Edit.jsx) — until now the
 * editor only ever read/wrote the store's DEFAULT locale, so a page created
 * in "pl" was never reachable at all in "en" (user-reported: /pl/test-page
 * had no /en counterpart). PageController::edit()/save() now resolve an
 * explicit ?locale=, falling back to the default locale's content as a
 * starting point when the requested locale has no translation row yet.
 */
final class CmsPageMultilingualEditingTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();
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

    public function test_editor_offers_every_enabled_language_and_flags_which_are_translated(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createPage($staff, 'test-page', 'Strona testowa');

        $response = $this->actingAs($staff)->get(route('panel.cms.pages.edit', $pageId));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('currentLocale', 'pl')->where('isUntranslated', false)->has('languages', 2));

        $props = $response->viewData('page')['props'];
        $languages = collect($props['languages'])->keyBy('code');
        $this->assertTrue($languages['pl']['hasTranslation']);
        $this->assertFalse($languages['en']['hasTranslation']);
    }

    public function test_requesting_an_untranslated_locale_shows_the_default_locales_content_as_a_starting_point(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createPage($staff, 'test-page', 'Strona testowa');

        $response = $this->actingAs($staff)->get(route('panel.cms.pages.edit', $pageId).'?locale=en');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('currentLocale', 'en')
            ->where('isUntranslated', true)
            ->where('data.root.props.title', 'Strona testowa')
            ->where('data.root.props.slug', 'test-page'));

        // Merely viewing the fallback must not have created an "en" row.
        $this->assertDatabaseMissing('cms_page_translations', ['page_id' => $pageId, 'locale' => 'en']);
    }

    public function test_saving_with_an_explicit_locale_creates_a_separate_translation_without_touching_the_default(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createPage($staff, 'test-page', 'Strona testowa');

        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId).'?locale=en', [
            'root' => ['props' => ['title' => 'Test page', 'slug' => 'test-page', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Hello', 'level' => '2']]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_page_translations', ['page_id' => $pageId, 'locale' => 'en', 'slug' => 'test-page', 'title' => 'Test page']);
        $plTranslation = DB::table('cms_page_translations')->where('page_id', $pageId)->where('locale', 'pl')->first();
        $this->assertSame('Strona testowa', $plTranslation->title);

        // The same literal slug for "pl" and "en" is allowed — uniqueness is per locale.
        $this->assertDatabaseHas('cms_page_translations', ['page_id' => $pageId, 'locale' => 'pl', 'slug' => 'test-page']);
    }

    public function test_the_created_translation_is_immediately_reachable_on_the_storefront_in_that_locale(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createPage($staff, 'test-page', 'Strona testowa');
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId).'?locale=en', [
            'root' => ['props' => ['title' => 'Test page', 'slug' => 'test-page-en', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Hello', 'level' => '2']]],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/en/test-page-en')->assertOk()->assertSee('Hello');
        $this->get('/en/test-page')->assertNotFound();
    }

    public function test_reopening_a_translated_locale_loads_its_own_content_not_the_default(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createPage($staff, 'test-page', 'Strona testowa');
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId).'?locale=en', [
            'root' => ['props' => ['title' => 'Test page', 'slug' => 'test-page-en', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Hello', 'level' => '2']]],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($staff)->get(route('panel.cms.pages.edit', $pageId).'?locale=en');

        $response->assertInertia(fn ($page) => $page
            ->where('isUntranslated', false)
            ->where('data.root.props.title', 'Test page')
            ->where('data.root.props.slug', 'test-page-en'));
    }

    private function createPage(StaffAccount $staff, string $slug, string $title): int
    {
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => $slug, 'title' => $title,
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('cms_pages')->latest('id')->value('id');
    }
}
