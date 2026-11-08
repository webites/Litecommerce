<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StaffManagementTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_administrator_can_create_staff_with_one_role_and_boolean_overrides(): void
    {
        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->post('/panel/staff', [
            'name' => 'Anna Magazyn',
            'email' => 'anna@example.com',
            'password' => 'StrongPassword123!',
            'role' => SystemStaffRole::WarehouseWorker->value,
            'permissions' => [
                CorePermission::ManageInventory->value => 'inherit',
                CorePermission::ManageContent->value => 'allow',
                CorePermission::ManageOrders->value => 'deny',
            ],
        ])->assertRedirect();

        $account = StaffAccount::query()->where('email', 'anna@example.com')->sole();
        $this->assertSame(SystemStaffRole::WarehouseWorker, $account->role);
        $this->assertSame([
            CorePermission::ManageContent->value => true,
            CorePermission::ManageOrders->value => false,
        ], $account->permission_overrides);
        $this->assertTrue(AuditEntry::query()->where('action', 'identity.staff.created')->exists());
    }

    public function test_authorized_staff_sees_management_page_and_navigation_capability(): void
    {
        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->get('/panel/staff')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Staff/Index')
                ->where('auth.can.manageStaff', true)
                ->has('staffAccounts.data', 1)
                ->has('roles', 4)
                ->has('permissions', 17));
    }

    public function test_role_without_manage_staff_permission_cannot_open_or_submit_management_screen(): void
    {
        $warehouseWorker = StaffAccount::factory()
            ->withRole(SystemStaffRole::WarehouseWorker)
            ->create();

        $this->actingAs($warehouseWorker)->get('/panel/staff')->assertForbidden();
        $this->actingAs($warehouseWorker)->post('/panel/staff', [])->assertForbidden();
    }

    public function test_deactivated_staff_cannot_log_in(): void
    {
        $administrator = StaffAccount::factory()->create();
        $staff = StaffAccount::factory()->create([
            'email' => 'worker@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $this->actingAs($administrator)->put('/panel/staff/'.$staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => null,
            'role' => SystemStaffRole::WarehouseWorker->value,
            'permissions' => [],
            'is_active' => false,
        ])->assertRedirect();

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'worker@example.com',
            'password' => 'StrongPassword123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_cannot_deactivate_current_account(): void
    {
        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->put('/panel/staff/'.$administrator->id, [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'password' => null,
            'role' => SystemStaffRole::Administrator->value,
            'permissions' => [],
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($administrator->refresh()->is_active);
    }

    public function test_unregistered_permission_key_is_rejected(): void
    {
        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->post('/panel/staff', [
            'name' => 'Plugin Tester',
            'email' => 'plugin@example.com',
            'password' => 'StrongPassword123!',
            'role' => SystemStaffRole::ContentEditor->value,
            'permissions' => ['unknown.plugin.permission' => 'allow'],
        ])->assertSessionHasErrors('permissions');
    }
}
