<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\EnvFileWriter;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\Warehouse;
use Tests\Concerns\UsesFakeInstallerDependencies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class InstallationHappyPathTest extends TestCase
{
    use UsesFakeInstallerDependencies;
    use UsesIsolatedStorage;

    public function test_full_wizard_creates_admin_warehouse_lock_and_migrates_database(): void
    {
        $this->useIsolatedStorage();
        $this->fakeInstallerDependencies();

        $this->get('/install')->assertOk();

        $this->post('/install/database', [
            'host' => 'mysql',
            'port' => '3306',
            'database' => 'litecommerce',
            'username' => 'litecommerce',
            'password' => 'litecommerce',
        ])->assertRedirect(route('install.administrator.show'));

        $env = (string) file_get_contents(app(EnvFileWriter::class)->path());
        $this->assertStringContainsString('APP_URL=http://localhost', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);

        $this->get('/install/administrator')->assertOk();

        $this->post('/install/administrator', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('install.finalize.show'));

        $this->get('/install/finalize')->assertOk();

        $this->post('/install/finalize')->assertRedirect(route('login'));

        $this->assertTrue(Schema::hasTable('setup_checks'));
        $this->assertTrue(Schema::hasTable('warehouses'));
        $this->assertTrue(Schema::hasTable('staff_accounts'));
        $this->assertTrue(Schema::hasTable('shop_currencies'));
        $this->assertTrue(Schema::hasTable('languages'));

        $this->assertSame(1, Warehouse::query()->count());
        $administrator = StaffAccount::query()->where('email', 'admin@example.com')->first();
        $this->assertNotNull($administrator);
        $this->assertSame(SystemStaffRole::Administrator, $administrator->role);
        $this->assertTrue(InstallationLock::exists());
    }
}
