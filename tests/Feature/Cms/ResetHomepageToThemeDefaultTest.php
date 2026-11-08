<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Application\ResetHomepageToThemeDefault;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * Cms\Application\ResetHomepageToThemeDefault — "starting data" for a fresh
 * shop or one that just switched themes (see docs/knowledge-base/09-presentation.md).
 * Imports the active theme's own theme::homepage default content into the
 * is_homepage=true CmsPage row, creating it if none exists yet.
 */
final class ResetHomepageToThemeDefaultTest extends TestCase
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

    public function test_creates_a_homepage_with_the_themes_default_content_when_none_exists_yet(): void
    {
        $this->installAndActivate($this->zipDirectory(base_path('resources/themes/default-storefront')));

        $page = app(ResetHomepageToThemeDefault::class)->handle('pl');

        $this->assertTrue($page->is_homepage);
        $this->assertSame('published', $page->status);
        $translation = DB::table('cms_page_translations')->where('page_id', $page->id)->first();
        $this->assertNotNull($translation->slug);
        $content = json_decode((string) $translation->content, true);
        $this->assertNotSame([], $content['content']);
        $this->assertSame('hero', $content['content'][0]['type']);
    }

    public function test_an_existing_homepages_content_is_overwritten_but_slug_and_flag_are_kept(): void
    {
        $this->installAndActivate($this->zipDirectory(base_path('resources/themes/default-storefront')));
        $first = app(ResetHomepageToThemeDefault::class)->handle('pl');
        DB::table('cms_page_translations')->where('page_id', $first->id)->update([
            'content' => json_encode(['content' => [['type' => 'rich-text', 'props' => ['html' => '<p>Własna treść</p>']]]]),
        ]);
        $slugBefore = DB::table('cms_page_translations')->where('page_id', $first->id)->value('slug');

        $second = app(ResetHomepageToThemeDefault::class)->handle('pl');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->is_homepage);
        $translation = DB::table('cms_page_translations')->where('page_id', $second->id)->first();
        $this->assertSame($slugBefore, $translation->slug);
        $content = json_decode((string) $translation->content, true);
        $this->assertSame('hero', $content['content'][0]['type']);
    }

    public function test_a_theme_without_its_own_default_homepage_falls_back_to_default_storefronts_copy(): void
    {
        // Same theme:: fallback as blocks/archetypes: a theme with no homepage.blade.php
        // of its own inherits default-storefront's, exactly like react-storefront does.
        $this->installAndActivate($this->stubThemeZip('stub-theme'));

        $page = app(ResetHomepageToThemeDefault::class)->handle('pl');

        $translation = DB::table('cms_page_translations')->where('page_id', $page->id)->first();
        $content = json_decode((string) $translation->content, true);
        $this->assertSame('hero', $content['content'][0]['type']);
    }

    private function installAndActivate(string $zipPath): void
    {
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();
    }

    private function stubThemeZip(string $id): string
    {
        $dir = sys_get_temp_dir().'/lc-stub-theme-'.uniqid();
        mkdir($dir.'/views/catalog', 0777, true);
        file_put_contents($dir.'/manifest.json', json_encode([
            'id' => $id, 'name' => 'Stub theme', 'version' => '1.0.0', 'type' => 'theme', 'engine' => 'blade',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/views/catalog/index.blade.php', '<p>stub</p>');

        return $this->zipDirectory($dir);
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
