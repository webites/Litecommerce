<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Providers;

use App\Support\Secrets\SecretReencryptor;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Identity\Application\EloquentCustomerAccountDeletion;
use Litecommerce\Modules\Identity\Application\EloquentStaffTwoFactorManager;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountDeletion;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;
use Litecommerce\Modules\Identity\Contracts\CustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Contracts\PermissionDefinition;
use Litecommerce\Modules\Identity\Contracts\PermissionRegistry;
use Litecommerce\Modules\Identity\Contracts\StaffAccountProvisioner;
use Litecommerce\Modules\Identity\Contracts\StaffCredentialVerifier;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\EloquentCustomerAccountLookup;
use Litecommerce\Modules\Identity\Infrastructure\EloquentCustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Infrastructure\EloquentStaffAccountProvisioner;
use Litecommerce\Modules\Identity\Infrastructure\EloquentStaffCredentialVerifier;
use Litecommerce\Modules\Identity\Infrastructure\EloquentStaffPermissionChecker;
use Litecommerce\Modules\Identity\Infrastructure\Permissions\InMemoryPermissionRegistry;
use Litecommerce\Modules\Identity\Infrastructure\StaffSecretReencryptor;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class, function (): PermissionRegistry {
            $registry = new InMemoryPermissionRegistry;

            foreach ($this->corePermissions() as $definition) {
                $registry->register($definition);
            }

            return $registry;
        });

        $this->app->bind(StaffAccountProvisioner::class, EloquentStaffAccountProvisioner::class);
        $this->app->tag(StaffSecretReencryptor::class, SecretReencryptor::class);
        $this->app->bind(StaffCredentialVerifier::class, EloquentStaffCredentialVerifier::class);
        $this->app->scoped(StaffPermissionChecker::class, EloquentStaffPermissionChecker::class);
        $this->app->bind(CustomerPurchaseBlockChecker::class, EloquentCustomerPurchaseBlockChecker::class);
        $this->app->bind(CustomerAccountLookup::class, EloquentCustomerAccountLookup::class);
        $this->app->bind(CustomerAccountDeletion::class, EloquentCustomerAccountDeletion::class);
        $this->app->bind(StaffTwoFactorManager::class, EloquentStaffTwoFactorManager::class);
    }

    public function boot(): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('identity.');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'identity');
    }

    /** @return list<PermissionDefinition> */
    private function corePermissions(): array
    {
        $administrator = SystemStaffRole::Administrator->value;
        $manager = SystemStaffRole::StoreManager->value;
        $warehouse = SystemStaffRole::WarehouseWorker->value;
        $content = SystemStaffRole::ContentEditor->value;

        return [
            new PermissionDefinition(CorePermission::AccessPanel->value, 'Dostęp do panelu', 'Panel', [$administrator, $manager, $warehouse, $content]),
            new PermissionDefinition(CorePermission::ManageStaff->value, 'Zarządzanie pracownikami', 'Pracownicy', [$administrator]),
            new PermissionDefinition(CorePermission::ManageStoreSettings->value, 'Zarządzanie ustawieniami sklepu', 'Sklep', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ManageEmail->value, 'Zarządzanie e-mailami', 'Sklep', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ManageCatalog->value, 'Zarządzanie katalogiem', 'Katalog', [$administrator, $manager, $content]),
            new PermissionDefinition(CorePermission::ManagePricing->value, 'Zarządzanie cenami', 'Ceny', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ManageInventory->value, 'Zarządzanie magazynem', 'Magazyn', [$administrator, $manager, $warehouse]),
            new PermissionDefinition(CorePermission::ManageDelivery->value, 'Zarządzanie dostawą', 'Dostawa', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ManageCoupons->value, 'Zarządzanie kuponami', 'Kupony', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ViewOrders->value, 'Wyświetlanie zamówień', 'Zamówienia', [$administrator, $manager, $warehouse]),
            new PermissionDefinition(CorePermission::ManageOrders->value, 'Obsługa zamówień', 'Zamówienia', [$administrator, $manager, $warehouse]),
            new PermissionDefinition(CorePermission::ManageRefunds->value, 'Obsługa zwrotów', 'Zamówienia', [$administrator, $manager]),
            new PermissionDefinition(CorePermission::ManageContent->value, 'Zarządzanie treścią', 'Treści', [$administrator, $manager, $content]),
            new PermissionDefinition(CorePermission::ManageExtensions->value, 'Zarządzanie rozszerzeniami', 'Rozszerzenia', [$administrator]),
            new PermissionDefinition(CorePermission::ManageThemes->value, 'Zarządzanie motywami', 'Motywy', [$administrator]),
            new PermissionDefinition(CorePermission::ManageUpdates->value, 'Aktualizacje sklepu', 'Sklep', [$administrator]),
            new PermissionDefinition(CorePermission::UseTestOrders->value, 'Korzystanie z trybu testowego', 'Testy', [$administrator]),
            new PermissionDefinition(CorePermission::ViewAudit->value, 'Wyświetlanie historii działań', 'Bezpieczeństwo', [$administrator, $manager]),
        ];
    }
}
