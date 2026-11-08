<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * <meta name="robots" content="noindex"> — the language-wide signal
 * (languages.noindex, Storefront\Providers\StorefrontServiceProvider's
 * theme:: composer). The product variant_seo_mode=noindex signal is
 * covered separately in StorefrontPagesTest, since it needs a product with
 * variants — this suite is about the language-level switch alone.
 */
final class RobotsMetaTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();
    }

    public function test_a_noindex_language_gets_the_robots_meta_tag_on_the_homepage(): void
    {
        $this->get('/en')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_a_regular_language_never_gets_the_robots_meta_tag(): void
    {
        $this->get('/pl')->assertOk()->assertDontSee('name="robots"', false);
    }

    public function test_a_noindex_language_covers_every_page_type_not_just_the_homepage(): void
    {
        $this->get('/en/products')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
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
