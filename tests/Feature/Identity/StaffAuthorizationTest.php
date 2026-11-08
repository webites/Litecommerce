<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use InvalidArgumentException;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StaffAuthorizationTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_administrator_with_explicit_denial_cannot_open_panel(): void
    {
        $administrator = StaffAccount::factory()->create([
            'permission_overrides' => [CorePermission::AccessPanel->value => false],
        ]);

        $this->actingAs($administrator)->get('/panel')->assertForbidden();
    }

    public function test_system_role_inherits_panel_access(): void
    {
        $warehouseWorker = StaffAccount::factory()
            ->withRole(SystemStaffRole::WarehouseWorker)
            ->create();

        $this->actingAs($warehouseWorker)->get('/panel')->assertRedirect(route('panel.wizard.show'));
    }

    public function test_changing_role_or_overrides_increments_permission_revision(): void
    {
        $account = StaffAccount::factory()->create();
        $this->assertSame(0, $account->refresh()->permission_revision);

        $account->update(['permission_overrides' => [CorePermission::ManageContent->value => false]]);
        $this->assertSame(1, $account->refresh()->permission_revision);

        $account->update(['role' => SystemStaffRole::ContentEditor]);
        $this->assertSame(2, $account->refresh()->permission_revision);
    }

    public function test_non_boolean_permission_override_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StaffAccount::factory()->create([
            'permission_overrides' => [CorePermission::ManageContent->value => 1],
        ]);
    }
}
