<?php

declare(strict_types=1);

namespace Tests\Feature\Themes;

use FilesystemIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\Events\ThemeActivated;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * Themes\Application\ActivateTheme dispatches Themes\Contracts\Events\ThemeActivated
 * after commit — the generic hook a theme or plugin listens to from its own
 * boot(), mirroring Orders\Contracts\Events\OrderPlaced exactly. See
 * docs/knowledge-base/09-presentation.md.
 */
final class ThemeActivationEventTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();
    }

    public function test_activating_a_theme_dispatches_theme_activated_with_its_id(): void
    {
        Event::fake([ThemeActivated::class]);
        $staff = StaffAccount::factory()->create();

        $upload = new UploadedFile($this->zipDirectory(base_path('resources/themes/default-storefront')), 'theme.zip', 'application/zip', null, true);
        $this->actingAs($staff)->post(route('panel.themes.store'), ['acknowledge_untrusted' => true, 'package' => $upload])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();

        Event::assertDispatched(ThemeActivated::class, fn (ThemeActivated $event): bool => $event->themeId === 'default-storefront');
    }

    public function test_reactivating_an_already_active_theme_does_not_redispatch(): void
    {
        Event::fake([ThemeActivated::class]);
        $staff = StaffAccount::factory()->create();

        $upload = new UploadedFile($this->zipDirectory(base_path('resources/themes/default-storefront')), 'theme.zip', 'application/zip', null, true);
        $this->actingAs($staff)->post(route('panel.themes.store'), ['acknowledge_untrusted' => true, 'package' => $upload])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(ThemeActivated::class, 1);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(ThemeActivated::class, 1);
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
