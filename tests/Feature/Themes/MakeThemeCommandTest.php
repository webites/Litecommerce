<?php

declare(strict_types=1);

namespace Tests\Feature\Themes;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * Coverage for `artisan theme:make`, the dev-time scaffold added alongside
 * the template-fallback decision — see "Moduł Themes"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013,
 * ustalenie 2026-09-17): since top-level views still have no fallback, a
 * new theme author needs a starting point that already has the full
 * required set, without having to also copy partials that now fall back.
 */
final class MakeThemeCommandTest extends TestCase
{
    private const REQUIRED_VIEWS = [
        'catalog/index.blade.php',
        'catalog/show.blade.php',
        'cart/show.blade.php',
        'checkout/show.blade.php',
        'checkout/confirmation.blade.php',
        'account/login.blade.php',
        'account/register.blade.php',
        'account/orders/index.blade.php',
        'account/orders/show.blade.php',
    ];

    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        $files = new Filesystem;
        foreach ($this->tempDirs as $dir) {
            $files->deleteDirectory($dir);
        }

        parent::tearDown();
    }

    private function tempDestination(string $suffix): string
    {
        $dir = sys_get_temp_dir().'/lc-theme-make-'.$suffix.'-'.uniqid();
        $this->tempDirs[] = $dir;

        return $dir;
    }

    public function test_it_scaffolds_the_required_top_level_views_but_not_partials(): void
    {
        $destination = $this->tempDestination('basic');

        $this->artisan('theme:make', ['id' => 'my-shop-theme', '--path' => $destination])
            ->assertExitCode(0);

        $manifest = json_decode((string) file_get_contents("{$destination}/manifest.json"), true);
        $this->assertSame('my-shop-theme', $manifest['id']);
        $this->assertSame('My Shop Theme', $manifest['name']);
        $this->assertSame('0.1.0', $manifest['version']);
        $this->assertArrayNotHasKey('dependencies', $manifest);
        $this->assertArrayNotHasKey('settings', $manifest);

        $this->assertFileExists("{$destination}/views/layout.blade.php");
        foreach (self::REQUIRED_VIEWS as $view) {
            $this->assertFileExists("{$destination}/views/{$view}");
        }
        $this->assertFileExists("{$destination}/assets/css/theme.css");
        $this->assertDirectoryDoesNotExist("{$destination}/views/partials");
    }

    public function test_with_partials_flag_also_copies_the_default_theme_partials(): void
    {
        $destination = $this->tempDestination('with-partials');

        $this->artisan('theme:make', ['id' => 'full-copy-theme', '--path' => $destination, '--with-partials' => true])
            ->assertExitCode(0);

        $this->assertFileExists("{$destination}/views/partials/product-card.blade.php");
    }

    public function test_name_option_overrides_the_generated_manifest_name(): void
    {
        $destination = $this->tempDestination('named');

        $this->artisan('theme:make', ['id' => 'acme-theme', '--path' => $destination, '--name' => 'Acme Storefront'])
            ->assertExitCode(0);

        $manifest = json_decode((string) file_get_contents("{$destination}/manifest.json"), true);
        $this->assertSame('Acme Storefront', $manifest['name']);
    }

    public function test_it_rejects_an_invalid_id_and_creates_nothing(): void
    {
        $destination = $this->tempDestination('invalid');

        $this->artisan('theme:make', ['id' => 'Not A Slug!', '--path' => $destination])
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($destination);
    }

    public function test_it_refuses_to_overwrite_an_existing_destination(): void
    {
        $destination = $this->tempDestination('existing');
        (new Filesystem)->ensureDirectoryExists($destination);
        file_put_contents("{$destination}/keep-me.txt", 'pre-existing');

        $this->artisan('theme:make', ['id' => 'clashing-theme', '--path' => $destination])
            ->assertExitCode(1);

        $this->assertFileExists("{$destination}/keep-me.txt");
        $this->assertFileDoesNotExist("{$destination}/manifest.json");
    }
}
