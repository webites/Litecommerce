<?php

declare(strict_types=1);

namespace Tests\Feature\Themes;

use FilesystemIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * End-to-end coverage of the P1-013 theme lifecycle (upload -> activate ->
 * deactivate -> delete), single-active-theme enforcement, dependency
 * checks and asset publishing — see
 * docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013.
 */
final class ThemeLifecycleTest extends TestCase
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

    private function defaultStorefrontZip(): string
    {
        return $this->zipDirectory(base_path('resources/themes/default-storefront'));
    }

    private function reactStorefrontZip(): string
    {
        return $this->zipDirectory(base_path('resources/themes/react-storefront'));
    }

    /** A trivial, manifest-only theme package — enough to exercise multi-theme switching without a full view set. */
    private function stubThemeZip(string $id, array $manifestOverrides = []): string
    {
        $dir = sys_get_temp_dir().'/lc-stub-theme-'.uniqid();
        mkdir($dir.'/views/catalog', 0777, true);
        file_put_contents($dir.'/manifest.json', json_encode(array_merge([
            'id' => $id, 'name' => 'Stub theme', 'version' => '1.0.0', 'type' => 'theme', 'engine' => 'blade',
        ], $manifestOverrides), JSON_THROW_ON_ERROR));
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

    private function upload(StaffAccount $staff, string $zipPath): void
    {
        $upload = new UploadedFile($zipPath, 'theme.zip', 'application/zip', null, true);
        $this->actingAs($staff)->post(route('panel.themes.store'), ['acknowledge_untrusted' => true, 'package' => $upload])->assertSessionHasNoErrors();
    }

    public function test_theme_upload_requires_acknowledgement_and_records_the_package_sha256(): void
    {
        $staff = StaffAccount::factory()->create();
        $zipPath = $this->stubThemeZip('hash-theme');
        $upload = fn () => new UploadedFile($zipPath, 'theme.zip', 'application/zip', null, true);

        $this->actingAs($staff)->post(route('panel.themes.store'), ['package' => $upload()])
            ->assertSessionHasErrors('acknowledge_untrusted');
        $this->assertDatabaseCount('themes', 0);

        $this->actingAs($staff)->post(route('panel.themes.store'), ['acknowledge_untrusted' => true, 'package' => $upload()])
            ->assertSessionHasNoErrors();

        $hash = hash_file('sha256', $zipPath);
        $this->assertDatabaseHas('themes', ['package_sha256' => $hash]);
        $entry = DB::table('audit_entries')->where('action', 'theme.package.uploaded')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString($hash, (string) $entry->metadata);
    }

    public function test_an_unknown_theme_redirects_to_the_listing_with_an_error(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.themes.show', 'unknown-theme'))
            ->assertRedirect(route('panel.themes.index'))
            ->assertSessionHas('error');
    }

    public function test_full_lifecycle_upload_activate_deactivate_delete(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->upload($staff, $this->defaultStorefrontZip());
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'installed']);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'active']);
        $this->assertNotNull(DB::table('themes')->where('id', 'default-storefront')->value('activated_at'));

        // Assets published under the public disk.
        $this->assertTrue(is_file(storage_path('app/public/themes/default-storefront/css/theme.css')));

        $this->actingAs($staff)->post(route('panel.themes.deactivate', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'installed']);
        $this->assertNull(DB::table('themes')->where('id', 'default-storefront')->value('activated_at'));

        $this->actingAs($staff)->delete(route('panel.themes.destroy', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('themes', ['id' => 'default-storefront']);
        $this->assertFalse(is_file(storage_path('app/public/themes/default-storefront/css/theme.css')));
    }

    public function test_activating_a_second_theme_deactivates_the_first(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->upload($staff, $this->defaultStorefrontZip());
        $this->upload($staff, $this->stubThemeZip('stub-theme'));

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'active']);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'stub-theme'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'stub-theme', 'status' => 'active']);
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'installed']);
    }

    public function test_deleting_an_active_theme_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->defaultStorefrontZip());
        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'));

        $this->actingAs($staff)->delete(route('panel.themes.destroy', 'default-storefront'))
            ->assertSessionHasErrors('theme');
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront']);
    }

    public function test_activation_is_rejected_when_a_required_plugin_is_not_enabled(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->stubThemeZip('needs-plugin', [
            'dependencies' => [['id' => 'hello-plugin', 'version' => '*']],
        ]));

        $this->actingAs($staff)->post(route('panel.themes.activate', 'needs-plugin'))
            ->assertSessionHasErrors('theme');
        $this->assertDatabaseHas('themes', ['id' => 'needs-plugin', 'status' => 'installed']);
    }

    public function test_theme_settings_are_saved_and_readable(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->stubThemeZip('settings-theme', [
            'settings' => [['key' => 'accent_color', 'type' => 'string', 'label' => 'Kolor', 'default' => '#000']],
        ]));

        $this->actingAs($staff)->put(route('panel.themes.settings.update', 'settings-theme'), [
            'values' => ['accent_color' => '#ff0000'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('theme_settings', ['theme_id' => 'settings-theme', 'key' => 'accent_color', 'value' => '#ff0000']);
    }

    public function test_uploading_the_same_theme_twice_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->defaultStorefrontZip());

        $upload = new UploadedFile($this->defaultStorefrontZip(), 'theme.zip', 'application/zip', null, true);
        $this->actingAs($staff)->post(route('panel.themes.store'), ['acknowledge_untrusted' => true, 'package' => $upload])
            ->assertSessionHasErrors('package');
    }

    public function test_staff_without_manage_themes_permission_is_forbidden(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->get(route('panel.themes.index'))->assertForbidden();
    }

    public function test_active_theme_resolver_reports_dark_mode_support_declared_in_the_manifest(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->stubThemeZip('dark-theme', ['supports_dark_mode' => true]));
        $this->actingAs($staff)->post(route('panel.themes.activate', 'dark-theme'))->assertSessionHasNoErrors();

        $this->assertTrue(app(ActiveThemeResolver::class)->supportsDarkMode());
    }

    public function test_active_theme_resolver_defaults_dark_mode_support_to_false(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->stubThemeZip('plain-theme'));
        $this->actingAs($staff)->post(route('panel.themes.activate', 'plain-theme'))->assertSessionHasNoErrors();

        $this->assertFalse(app(ActiveThemeResolver::class)->supportsDarkMode());
    }

    public function test_the_blade_react_reference_theme_installs_and_activates(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->upload($staff, $this->reactStorefrontZip());
        $this->assertDatabaseHas('themes', ['id' => 'react-storefront', 'status' => 'installed', 'engine' => 'blade-react']);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'react-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'react-storefront', 'status' => 'active']);
        $this->assertTrue(is_file(storage_path('app/public/themes/react-storefront/js/app.js')));
    }

    public function test_switching_between_the_two_reference_themes_deactivates_the_other_each_time(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->upload($staff, $this->defaultStorefrontZip());
        $this->upload($staff, $this->reactStorefrontZip());

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'active']);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'react-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'react-storefront', 'status' => 'active']);
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'installed']);

        $this->actingAs($staff)->post(route('panel.themes.activate', 'default-storefront'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('themes', ['id' => 'default-storefront', 'status' => 'active']);
        $this->assertDatabaseHas('themes', ['id' => 'react-storefront', 'status' => 'installed']);
    }
}
