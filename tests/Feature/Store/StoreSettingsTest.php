<?php

declare(strict_types=1);

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StoreSettingsTest extends TestCase
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

    public function test_authorized_staff_can_update_settings_through_the_public_reader(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.settings.update'), [
            'store_name' => 'Sklep Testowy',
            'blog_enabled' => false,
            'products_per_page' => 18,
        ])->assertSessionHasNoErrors();

        $settings = app(StoreSettingsReader::class)->current();
        $this->assertSame('Sklep Testowy', $settings->storeName);
        $this->assertFalse($settings->blogEnabled);
        $this->assertSame(18, $settings->catalogProductsPerPage);
        $this->assertSame('Sklep Testowy', app(GlobalTagRegistry::class)->resolve('pl')['store_name']);
        $this->get('/pl/blog')->assertNotFound();
        $this->get('/pl/sitemap-posts.xml')->assertNotFound();
    }

    public function test_logo_can_be_replaced_and_removed_without_leaving_old_media(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('first.png', 120, 40),
        ])->assertSessionHasNoErrors();
        $firstId = app(StoreSettingsStore::class)->get(StoreSettingKeys::StoreLogoMediaId);

        $this->actingAs($staff)->post(route('panel.settings.logo.store'), [
            'logo' => UploadedFile::fake()->image('second.png', 120, 40),
        ])->assertSessionHasNoErrors();
        $secondId = app(StoreSettingsStore::class)->get(StoreSettingKeys::StoreLogoMediaId);

        $this->assertNotSame($firstId, $secondId);
        $this->assertDatabaseMissing('media', ['id' => $firstId]);
        $this->assertDatabaseHas('media', ['id' => $secondId]);

        $this->actingAs($staff)->delete(route('panel.settings.logo.destroy'))->assertSessionHasNoErrors();
        $this->assertFalse(app(StoreSettingsStore::class)->has(StoreSettingKeys::StoreLogoMediaId));
        $this->assertDatabaseMissing('media', ['id' => $secondId]);
    }

    public function test_public_store_supports_typed_create_update_list_and_remove_operations(): void
    {
        $settings = app(StoreSettingsStore::class);

        $settings->set('plugin.example.enabled', true);
        $settings->setMany([
            'plugin.example.limit' => 12,
            'plugin.example.options' => ['mode' => 'compact'],
            'plugin.example.optional' => null,
        ]);

        $this->assertTrue($settings->get('plugin.example.enabled'));
        $this->assertSame(12, $settings->get('plugin.example.limit'));
        $this->assertSame(['mode' => 'compact'], $settings->get('plugin.example.options'));
        $this->assertTrue($settings->has('plugin.example.optional'));
        $this->assertNull($settings->get('plugin.example.optional', 'fallback'));
        $this->assertCount(4, $settings->all('plugin.example.'));

        $settings->set('plugin.example.limit', 24);
        $this->assertSame(24, $settings->get('plugin.example.limit'));
        $this->assertTrue($settings->remove('plugin.example.limit'));
        $this->assertFalse($settings->has('plugin.example.limit'));
        $this->assertFalse($settings->remove('plugin.example.limit'));
        $this->assertSame('fallback', $settings->get('plugin.example.missing', 'fallback'));
    }

    public function test_key_value_migration_preserves_existing_legacy_settings(): void
    {
        $migration = require database_path('migrations/2026_09_25_181000_convert_store_settings_to_key_value.php');
        $migration->down();
        DB::table('store_settings')->where('id', 1)->update([
            'store_name' => 'Sklep po migracji',
            'blog_enabled' => false,
        ]);

        $migration->up();

        $settings = app(StoreSettingsStore::class);
        $this->assertSame('Sklep po migracji', $settings->get(StoreSettingKeys::StoreName));
        $this->assertFalse($settings->get(StoreSettingKeys::BlogEnabled));
    }

    public function test_staff_without_store_settings_permission_is_forbidden(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->get(route('panel.settings.tabs.show', 'general'))->assertNotFound();
        $this->actingAs($staff)->put(route('panel.settings.update'), [
            'store_name' => 'Nieautoryzowana zmiana',
            'blog_enabled' => false,
            'products_per_page' => 24,
        ])->assertForbidden();
        $this->assertSame('Litecommerce', app(StoreSettingsReader::class)->current()->storeName);
    }

    public function test_settings_are_served_as_tabs_from_one_screen(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.settings.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Store/Settings/Index')
                ->has('settingsTabs', 11));

        $this->actingAs($staff)->getJson(route('panel.settings.tabs.show', 'email'))
            ->assertOk()
            ->assertJsonPath('id', 'email')
            ->assertJsonPath('component', 'email');

        $this->actingAs($staff)->getJson(route('panel.settings.tabs.show', 'delivery'))
            ->assertOk()
            ->assertJsonPath('id', 'delivery')
            ->assertJsonPath('component', 'delivery');

        $this->get('/panel/delivery')->assertNotFound();
        $this->get('/panel/payment-settings')->assertNotFound();
        $this->get('/panel/pricing')->assertNotFound();
        $this->get('/panel/languages')->assertNotFound();
        $this->get('/panel/seo')->assertNotFound();
        $this->get('/panel/redirects')->assertNotFound();
    }

    public function test_authorized_staff_can_clear_only_litecommerce_object_cache(): void
    {
        $staff = StaffAccount::factory()->create();
        $cache = app(ObjectCache::class);
        $cache->put('core.test.panel', 'entry', 'stale');
        Cache::put('scheduler:traffic:lock', 'preserved', 300);

        $this->actingAs($staff)
            ->from(route('panel.settings.index', ['tab' => 'advanced']))
            ->post(route('panel.settings.cache.clear'))
            ->assertRedirect(route('panel.settings.index', ['tab' => 'advanced']))
            ->assertSessionHas('status', trans('cache::panel.cleared'));

        $this->assertNull($cache->get('core.test.panel', 'entry'));
        $this->assertSame('preserved', Cache::get('scheduler:traffic:lock'));
        $this->assertDatabaseHas('audit_entries', [
            'actor_type' => 'staff',
            'actor_id' => (string) $staff->id,
            'action' => 'cache.application.cleared',
            'subject_type' => 'application_cache',
        ]);
    }

    public function test_cache_controls_expose_status_and_require_store_settings_permission(): void
    {
        $administrator = StaffAccount::factory()->create();
        $warehouse = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($administrator)->getJson(route('panel.settings.tabs.show', 'advanced'))
            ->assertOk()
            ->assertJsonPath('component', 'advanced')
            ->assertJsonPath('props.cache.store', 'array')
            ->assertJsonPath('props.cache.available', true);

        $this->actingAs($warehouse)->post(route('panel.settings.cache.clear'))->assertForbidden();
    }

    public function test_settings_screen_copy_is_available_in_polish_and_english(): void
    {
        $this->assertSame('Ustawienia sklepu', trans('store::panel.settings.title', [], 'pl'));
        $this->assertSame('Store settings', trans('store::panel.settings.title', [], 'en'));
        $this->assertSame('Wgraj dane demonstracyjne', trans('store::panel.settings.demo_install', [], 'pl'));
        $this->assertSame('Install demo data', trans('store::panel.settings.demo_install', [], 'en'));
    }
}
