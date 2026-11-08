<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageRegistry;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Litecommerce\Modules\Updates\Application\BuildReleasePackage;
use Litecommerce\Modules\Updates\Application\CheckForUpdates;
use Litecommerce\Modules\Updates\Application\CompatibilityChecker;
use Litecommerce\Modules\Updates\Application\EnvironmentProbe;
use Litecommerce\Modules\Updates\Application\FileSwapper;
use Litecommerce\Modules\Updates\Application\PackageVerifier;
use Litecommerce\Modules\Updates\Application\ReleaseManifestParser;
use Litecommerce\Modules\Updates\Application\RunUpdate;
use Litecommerce\Modules\Updates\Application\Steps\BackupDatabaseStep;
use Litecommerce\Modules\Updates\Application\Steps\CompleteUpdateStep;
use Litecommerce\Modules\Updates\Application\Steps\DownloadPackageStep;
use Litecommerce\Modules\Updates\Application\Steps\EnableMaintenanceStep;
use Litecommerce\Modules\Updates\Application\Steps\ExtractPackageStep;
use Litecommerce\Modules\Updates\Application\Steps\PostUpdateStep;
use Litecommerce\Modules\Updates\Application\Steps\PreflightStep;
use Litecommerce\Modules\Updates\Application\Steps\RunMigrationsStep;
use Litecommerce\Modules\Updates\Application\Steps\SwapFilesStep;
use Litecommerce\Modules\Updates\Application\Steps\VerifyIntegrityStep;
use Litecommerce\Modules\Updates\Application\Steps\VerifyPackageStep;
use Litecommerce\Modules\Updates\Application\UpdateMessages;
use Litecommerce\Modules\Updates\Application\UpdateOperationStore;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateRunLock;
use Litecommerce\Modules\Updates\Application\UpdateStateStore;
use Litecommerce\Modules\Updates\Application\VerifiedReleaseReader;
use Litecommerce\Modules\Updates\Console\BuildReleaseCommand;
use Litecommerce\Modules\Updates\Console\GenerateSigningKeyCommand;
use Litecommerce\Modules\Updates\Contracts\PackageSignatureVerifier;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Contracts\UpdateInstaller;
use Litecommerce\Modules\Updates\Infrastructure\FileUpdateOperationStore;
use Litecommerce\Modules\Updates\Infrastructure\FileUpdateRunLock;
use Litecommerce\Modules\Updates\Infrastructure\FileUpdateStateStore;
use Litecommerce\Modules\Updates\Infrastructure\GitHubReleaseSource;
use Litecommerce\Modules\Updates\Infrastructure\NativeEnvironmentProbe;
use Litecommerce\Modules\Updates\Infrastructure\SodiumPackageSignatureVerifier;
use Litecommerce\Modules\Updates\Presentation\Controllers\UpdatesController;

final class UpdatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PackageSignatureVerifier::class, fn () => new SodiumPackageSignatureVerifier(config('updates.trusted_public_keys')));
        $this->app->bind(BuildReleasePackage::class, fn ($app) => new BuildReleasePackage($app->make(EnvironmentRequirements::class), config('updates.package.exclude')));
        $this->app->bind(ReleaseSource::class, fn () => new GitHubReleaseSource((string) config('updates.repository')));
        $this->app->bind(EnvironmentProbe::class, NativeEnvironmentProbe::class);
        $this->app->bind(UpdateStateStore::class, FileUpdateStateStore::class);
        $this->app->bind(CompatibilityChecker::class, fn ($app) => new CompatibilityChecker(
            $app->make(EnvironmentProbe::class),
            config('updates.writable_paths'),
            (int) config('updates.minimum_free_bytes'),
        ));
        $this->app->bind(UpdateOperationStore::class, FileUpdateOperationStore::class);
        $this->app->bind(UpdateRunLock::class, FileUpdateRunLock::class);
        $this->app->bind(UpdatePaths::class, fn () => new UpdatePaths(base_path(), storage_path('app/updates'), (string) config('backup.directory'), config('updates.protected')));
        $this->app->bind(PackageVerifier::class, fn ($app) => new PackageVerifier(
            $app->make(PackageSignatureVerifier::class), $app->make(ReleaseManifestParser::class),
            (int) config('updates.max_uncompressed_bytes'), (int) config('updates.max_compression_ratio'),
        ));
        $this->app->bind(UpdateInstaller::class, fn ($app) => new RunUpdate(
            [
                new PreflightStep($app->make(VerifiedReleaseReader::class), $app->make(CompatibilityChecker::class), (string) config('version.current')),
                $app->make(EnableMaintenanceStep::class),
                $app->make(BackupDatabaseStep::class),
                new DownloadPackageStep($app->make(ReleaseSource::class), $app->make(UpdatePaths::class), (int) config('updates.max_archive_bytes')),
                $app->make(VerifyPackageStep::class),
                $app->make(ExtractPackageStep::class),
                $app->make(SwapFilesStep::class),
                $app->make(RunMigrationsStep::class),
                $app->make(PostUpdateStep::class),
                $app->make(VerifyIntegrityStep::class),
                $app->make(CompleteUpdateStep::class),
            ],
            $app->make(UpdateOperationStore::class), $app->make(FileSwapper::class), $app->make(UpdatePaths::class), $app->make(MaintenanceMode::class), $app->make(UpdateRunLock::class),
        ));
        $this->app->bind(UpdateChecker::class, fn ($app) => $app->make(CheckForUpdates::class, ['currentVersion' => (string) config('version.current')]));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'updates');
        $this->commands([GenerateSigningKeyCommand::class, BuildReleaseCommand::class]);

        $this->app->make(SettingsTabRegistry::class)->register(
            'updates',
            'updates::panel.tab',
            800,
            fn (SettingsTabContext $context) => $this->app->make(UpdatesController::class)->settingsTab($context),
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageUpdates->value),
        );

        $this->app->make(PanelMessageRegistry::class)->register(UpdateMessages::SOURCE, fn (): array => $this->app->make(UpdateMessages::class)->messages());

        $this->app->make(ScheduledTaskRegistry::class)->register('core.updates.check_releases', 86400, function (): void {
            $updates = $this->app->make(UpdateChecker::class);

            if ($updates->status()->automaticChecks) {
                $updates->checkNow();
            }
        });
    }
}
