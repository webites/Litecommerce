<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Domain\SetupCheckStatus;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Installer\Infrastructure\Models\SetupCheck;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\Warehouse;
use Tests\Concerns\UsesFakeInstallerDependencies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ReinstallationIsRejectedTest extends TestCase
{
    use UsesFakeInstallerDependencies;
    use UsesIsolatedStorage;

    public function test_finalize_is_unreachable_without_completing_earlier_steps_in_session(): void
    {
        $this->useIsolatedStorage();

        $this->get('/install/finalize')->assertRedirect(route('install.database.show'));
    }

    public function test_installer_post_routes_redirect_away_once_lock_file_exists(): void
    {
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        $this->post('/install/database', [
            'host' => 'mysql',
            'port' => '3306',
            'database' => 'litecommerce',
            'username' => 'litecommerce',
            'password' => 'litecommerce',
        ])->assertRedirect(route('login'));
    }

    public function test_resubmitting_finalize_after_partial_failure_does_not_duplicate_admin_or_warehouse(): void
    {
        $this->useIsolatedStorage();
        $this->fakeInstallerDependencies();

        $this->post('/install/database', [
            'host' => 'mysql',
            'port' => '3306',
            'database' => 'litecommerce',
            'username' => 'litecommerce',
            'password' => 'litecommerce',
        ])->assertRedirect(route('install.administrator.show'));

        $this->post('/install/administrator', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('install.finalize.show'));

        // Simulate a request that crashed after the admin/warehouse steps
        // completed but before the lock file was written.
        $this->artisan('migrate', ['--force' => true]);
        StaffAccount::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'Password123']);
        Warehouse::query()->create(['name' => 'Magazyn główny']);
        SetupCheck::query()->create(['step_key' => 'admin_account', 'status' => SetupCheckStatus::Done, 'completed_at' => now()]);
        SetupCheck::query()->create(['step_key' => 'warehouse', 'status' => SetupCheckStatus::Done, 'completed_at' => now()]);

        $this->assertFalse(InstallationLock::exists());

        $this->post('/install/finalize')->assertRedirect(route('login'));

        $this->assertSame(1, StaffAccount::query()->where('email', 'admin@example.com')->count());
        $this->assertSame(1, Warehouse::query()->count());
        $this->assertTrue(InstallationLock::exists());
    }
}
