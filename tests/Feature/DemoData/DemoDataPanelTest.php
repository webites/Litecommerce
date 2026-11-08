<?php

declare(strict_types=1);

namespace Tests\Feature\DemoData;

use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Application\SetBaseCurrency;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class DemoDataPanelTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        app(SetBaseCurrency::class)->handle('PLN', 'Polski złoty', 'zł', 2);
        app(WarehouseProvisioner::class)->ensureDefaultWarehouseExists();
    }

    public function test_administrator_can_install_inspect_and_remove_demo_data_from_advanced_settings(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.settings.tabs.show', 'advanced'))
            ->assertJsonPath('component', 'advanced')
            ->assertJsonPath('props.demoData.active', false)
            ->assertJsonPath('props.demoData.resource_count', 0);

        $this->actingAs($staff)->post(route('panel.settings.demo-data.store'))
            ->assertRedirect()
            ->assertSessionHas('status', trans('demo-data::panel.installed', ['count' => 181]));

        $this->assertDatabaseCount('products', 18);
        $this->assertDatabaseCount('blog_posts', 12);

        $this->actingAs($staff)->get(route('panel.settings.tabs.show', 'advanced'))
            ->assertJsonPath('props.demoData.active', true)
            ->assertJsonPath('props.demoData.status', 'ready')
            ->assertJsonPath('props.demoData.resource_count', 181)
            ->assertJsonPath('props.demoData.locale', 'pl');

        $this->actingAs($staff)->post(route('panel.settings.demo-data.store'))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseCount('products', 18);

        $this->actingAs($staff)->delete(route('panel.settings.demo-data.destroy'))
            ->assertRedirect()
            ->assertSessionHas('status', trans('demo-data::panel.removed', ['count' => 181]));

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('blog_posts', 0);
        $this->assertDatabaseHas('demo_data_batches', ['status' => 'removed', 'active_key' => null]);
    }

    public function test_staff_without_manage_store_settings_permission_cannot_manage_demo_data(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->post(route('panel.settings.demo-data.store'))->assertForbidden();
        $this->actingAs($staff)->delete(route('panel.settings.demo-data.destroy'))->assertForbidden();
        $this->assertDatabaseCount('demo_data_batches', 0);
    }
}
