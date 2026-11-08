<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\AdministratorData;
use Litecommerce\Modules\Identity\Contracts\StaffAccountProvisioner;
use Litecommerce\Modules\Installer\Domain\SetupCheckStatus;
use Litecommerce\Modules\Installer\Infrastructure\DefaultThemePackager;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Installer\Infrastructure\Models\SetupCheck;
use Litecommerce\Modules\Media\Contracts\PublicStorageLinker;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Throwable;

/**
 * Install finalization is split into retry-safe steps because shared hosting
 * may terminate a request while migrations or initial records are written.
 */
final class FinalizeInstallation
{
    public function __construct(
        private readonly StaffAccountProvisioner $staffAccountProvisioner,
        private readonly WarehouseProvisioner $warehouseProvisioner,
        private readonly PublicStorageLinker $publicStorageLinker,
        private readonly ThemeInstaller $themeInstaller,
        private readonly DefaultThemePackager $themePackager,
    ) {}

    /** @param array{name:string,email:string,hashed_password:string} $administrator */
    public function handle(array $administrator): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->step('admin_account', function () use ($administrator): void {
            $this->staffAccountProvisioner->ensureAdministratorExists(new AdministratorData(
                name: $administrator['name'],
                email: $administrator['email'],
                hashedPassword: $administrator['hashed_password'],
            ));
        });

        $this->step('warehouse', fn () => $this->warehouseProvisioner->ensureDefaultWarehouseExists());
        $this->step('public_storage', fn () => $this->publicStorageLinker->ensureLinked());
        $this->step('default_theme', function (): void {
            $zipPath = $this->themePackager->package();

            try {
                $this->themeInstaller->installAndActivate($zipPath);
            } finally {
                @unlink($zipPath);
            }
        });
        $this->step('lock', fn () => InstallationLock::create());
    }

    private function step(string $key, Closure $operation): void
    {
        $check = SetupCheck::query()->firstOrCreate(
            ['step_key' => $key],
            ['status' => SetupCheckStatus::Pending],
        );

        if ($check->status === SetupCheckStatus::Done) {
            return;
        }

        try {
            DB::transaction($operation);
            $check->update(['status' => SetupCheckStatus::Done, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $check->update(['status' => SetupCheckStatus::Failed, 'meta' => ['error' => $e->getMessage()]]);

            throw $e;
        }
    }
}
