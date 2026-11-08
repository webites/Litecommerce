<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class MaintenanceModeTest extends TestCase
{
    use UsesIsolatedStorage;

    private MaintenanceMode $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->maintenance = app(MaintenanceMode::class);
    }

    public function test_the_flag_is_a_file_with_a_stored_reason_and_cannot_be_enabled_twice(): void
    {
        $this->assertFalse($this->maintenance->isActive());
        $this->assertTrue($this->maintenance->enable(MaintenanceReason::Manual, '  Wracamy o 12  ', '7', 120));
        $this->assertFalse($this->maintenance->enable(MaintenanceReason::Update));

        $state = $this->maintenance->state();
        $this->assertSame(MaintenanceReason::Manual, $state->reason);
        $this->assertSame('Wracamy o 12', $state->message);
        $this->assertSame(120, $state->retryAfterSeconds);
        $this->assertSame('7', $state->actorId);
        $this->assertFalse($state->pausesBackgroundTasks());
        $this->assertFileExists(storage_path('maintenance.json'));

        $this->assertTrue($this->maintenance->disable());
        $this->assertFalse($this->maintenance->disable());
        $this->assertNull($this->maintenance->state());
    }

    public function test_storefront_pages_answer_503_with_a_theme_independent_page_and_retry_after(): void
    {
        $this->maintenance->enable(MaintenanceReason::Manual, 'Aktualizujemy sklep.', null, 90);

        $this->get('/pl', ['Accept-Language' => 'pl-PL,pl;q=0.9'])->assertStatus(503)->assertHeader('Retry-After', '90')->assertSee('Aktualizujemy sklep.')->assertSee('Przerwa techniczna', false);
        $this->get('/pl/products')->assertStatus(503);
        $this->get('/sitemap.xml')->assertStatus(503);
        $this->get('/', ['Accept-Language' => 'en'])->assertStatus(503)->assertSee('Scheduled maintenance');
    }

    public function test_json_endpoints_get_a_machine_readable_503_and_purchases_stay_blocked(): void
    {
        $this->maintenance->enable(MaintenanceReason::Update);

        foreach ([['POST', '/cart/items'], ['POST', '/checkout'], ['POST', '/customer/login'], ['GET', '/cart']] as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(503)->assertJsonPath('code', 'maintenance')->assertHeader('Retry-After');
        }
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_panel_sign_in_and_health_check_stay_reachable(): void
    {
        $this->maintenance->enable(MaintenanceReason::Update);

        $this->get('/login')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_staff_can_browse_the_storefront_read_only_but_never_reach_purchase_endpoints(): void
    {
        $this->maintenance->enable(MaintenanceReason::Update);
        $staff = StaffAccount::factory()->create();
        $this->get('/robots.txt')->assertStatus(503);

        $this->actingAs($staff)->get('/robots.txt')->assertOk();
        $this->actingAs($staff)->get('/pl/cart')->assertStatus(503);
        $this->actingAs($staff)->get('/pl/checkout')->assertStatus(503);
        $this->actingAs($staff)->postJson('/cart/items', ['variant_id' => 1, 'quantity' => '1'])->assertStatus(503);
        $this->actingAs($staff)->postJson('/customer/login', [])->assertStatus(503);
        $this->actingAs($staff)->post('/marketing/unsubscribe/'.str_repeat('a', 40))->assertStatus(503);
    }

    public function test_an_unauthorised_staff_role_without_panel_access_gets_no_preview(): void
    {
        $this->maintenance->enable(MaintenanceReason::Manual);
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();
        $staff->forceFill(['permission_overrides' => ['core.panel.access' => false]])->save();

        $this->actingAs($staff)->get('/robots.txt')->assertStatus(503);
    }

    public function test_an_update_pauses_scheduled_tasks_but_a_manual_pause_does_not(): void
    {
        $ran = 0;
        app(ScheduledTaskRegistry::class)->register('test.maintenance_probe', 60, function () use (&$ran): void {
            $ran++;
        });

        $this->maintenance->enable(MaintenanceReason::Update);
        app(RunDueScheduledTasks::class)->run(50, 5);
        $this->assertSame(0, $ran);

        $this->maintenance->disable();
        $this->maintenance->enable(MaintenanceReason::Manual);
        app(RunDueScheduledTasks::class)->run(50, 5);
        $this->assertSame(1, $ran);
    }

    public function test_the_panel_toggles_maintenance_with_audit_and_requires_the_settings_permission(): void
    {
        $admin = StaffAccount::factory()->create();

        $this->actingAs($admin)->post(route('panel.settings.maintenance.enable'), ['message' => str_repeat('a', 501)])->assertSessionHasErrors('message');
        $this->actingAs($admin)->post(route('panel.settings.maintenance.enable'), ['message' => 'Krótka przerwa'])->assertSessionHas('status');
        $this->assertSame('Krótka przerwa', $this->maintenance->state()->message);
        $this->assertDatabaseHas('audit_entries', ['action' => 'maintenance.enabled', 'actor_id' => (string) $admin->getKey()]);

        $this->actingAs($admin)->post(route('panel.settings.maintenance.enable'))->assertSessionHas('error');

        $this->actingAs($admin)->get(route('panel.settings.tabs.show', 'advanced'))->assertOk()
            ->assertJsonPath('props.maintenance.active', true)->assertJsonPath('props.maintenance.reason', 'manual');
        $this->actingAs($admin)->get(route('panel.settings.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('maintenance.reason', 'manual')->where('maintenance.message', 'Krótka przerwa'));

        $this->actingAs($admin)->delete(route('panel.settings.maintenance.disable'))->assertSessionHas('status');
        $this->assertFalse($this->maintenance->isActive());
        $this->assertDatabaseHas('audit_entries', ['action' => 'maintenance.disabled']);
        $this->actingAs($admin)->delete(route('panel.settings.maintenance.disable'))->assertSessionHas('error');

        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();
        $this->actingAs($editor)->post(route('panel.settings.maintenance.enable'))->assertForbidden();
        $this->actingAs($editor)->delete(route('panel.settings.maintenance.disable'))->assertForbidden();
        $this->assertFalse($this->maintenance->isActive());
    }
}
